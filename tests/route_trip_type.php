<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/config.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$source=getDBConnection();$sourceDb=$source->query('SELECT DATABASE()')->fetch_row()[0];
$db='gslip_trip_test_'.bin2hex(random_bytes(6));$source->query("CREATE DATABASE `$db`");
$sandbox=sys_get_temp_dir().'/gslip_trip_test_'.bin2hex(random_bytes(6));$server=null;$checks=0;
function tripCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);++$checks;}
function routePost($fields){global $address;$context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'timeout'=>10,'header'=>'Content-Type: application/x-www-form-urlencoded','content'=>http_build_query($fields)]]);$body=file_get_contents('http://'.$address.'/routes.php',false,$context);return json_decode($body,true,512,JSON_THROW_ON_ERROR);}
try {
    $conn=new mysqli('localhost','root','',$db);
    foreach(['routes','areas','route_locations'] as $table) $conn->query("CREATE TABLE `$table` LIKE `$sourceDb`.`$table`");
    if($conn->query("SHOW COLUMNS FROM routes LIKE 'trip_type'")->num_rows) $conn->query('ALTER TABLE routes DROP COLUMN trip_type');
    $conn->query("INSERT INTO areas(id,area_name) VALUES(1,'Trip test area')");
    $conn->query("INSERT INTO routes(id,origin,destination,distance_km,fuel_allocation,is_fixed_fuel,status) VALUES(1,'Legacy origin','Legacy destination',123.45,6.78,0,'active')");
    $conn->query(file_get_contents(__DIR__.'/../migrations/20261105_add_route_trip_type.sql'));
    $legacy=$conn->query('SELECT * FROM routes WHERE id=1')->fetch_assoc();
    tripCheck($legacy['trip_type']==='round_trip' && $legacy['distance_km']==='123.45' && $legacy['fuel_allocation']==='6.78','Migration defaults old routes without changing distances/fuel');
    mkdir($sandbox.'/includes',0777,true);copy(__DIR__.'/../routes.php',$sandbox.'/routes.php');
    file_put_contents($sandbox.'/includes/config.php','<?php function getDBConnection(){return new mysqli("localhost","root","",'.var_export($db,true).');}');
    file_put_contents($sandbox.'/router.php','<?php ini_set("display_errors","0");session_save_path(__DIR__);session_start();$_SESSION=["user_id"=>1,"session_token"=>"test","role"=>"admin","department_id"=>1,"area"=>1];session_write_close();return false;');
    $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$sandbox,$sandbox.'/router.php'],[0=>['pipe','r'],1=>['file',$sandbox.'/server.log','a'],2=>['file',$sandbox.'/server.log','a']],$pipes);fclose($pipes[0]);
    for($i=0;$i<30;$i++){$socket=@stream_socket_client('tcp://'.$address,$errno,$errstr,.1);if($socket){fclose($socket);break;}usleep(100000);}
    $base=['form_mode'=>'add','area'=>'Trip test area','origin'=>'New origin','destination'=>'New destination','distance_km'=>'999','route'=>'TEST01','is_two_wheels_route'=>'1','status'=>'active','remarks'=>'Test'];
    $map=['origin'=>['lat'=>9,'lng'=>118],'destinations'=>[['lat'=>10,'lng'=>119],['lat'=>11,'lng'=>120]],'route_distance'=>30,'return_distance'=>0,'final_distance'=>30,'trip_type'=>'one_way'];
    $r=routePost($base+['trip_type'=>'one_way','route_data'=>json_encode($map)]);
    tripCheck($r['status']==='success','One-way save');
    $saved=$conn->query("SELECT * FROM routes WHERE origin='New origin'")->fetch_assoc();$id=(int)$saved['id'];
    tripCheck($saved['trip_type']==='one_way' && $saved['distance_km']==='30.00' && $saved['fuel_allocation']==='2.20','One-way persistence and existing formula');
    $pins=$conn->query("SELECT latitude,longitude,location_type,sequence FROM route_locations WHERE route_id=$id ORDER BY sequence")->fetch_all(MYSQLI_ASSOC);
    tripCheck(count($pins)===3 && (float)$pins[2]['latitude']===11.0,'All map coordinates saved in order');
    $loaded=json_decode(file_get_contents('http://'.$address.'/routes.php?action=get_route_locations&route_id='.$id),true);
    tripCheck($loaded['route']['trip_type']==='one_way' && count($loaded['locations'])===3,'Edit endpoint restores trip type and coordinates');
    $edit=array_replace($base,['form_mode'=>'edit','routes_id'=>$id,'trip_type'=>'one_way','route_data'=>'','distance_km'=>'999','fuel_allocation'=>'2.20','remarks'=>'Edited']);
    tripCheck(routePost($edit)['status']==='success','Metadata-only edit saves');
    tripCheck($conn->query("SELECT distance_km FROM routes WHERE id=$id")->fetch_row()[0]==='30.00','Metadata edit preserves authoritative distance');
    tripCheck($conn->query("SELECT latitude,longitude,location_type,sequence FROM route_locations WHERE route_id=$id ORDER BY sequence")->fetch_all(MYSQLI_ASSOC)===$pins,'Metadata edit preserves coordinates');
    $beforeUnlock=$conn->query("SELECT * FROM routes WHERE id=$id")->fetch_assoc();
    $unfinalized=routePost(array_replace($edit,['route_map_dirty'=>'1','distance_km'=>'','fuel_allocation'=>'','remarks'=>'Must not save']));
    tripCheck($unfinalized['status']==='error' && str_contains($unfinalized['message'],'finalize'),'Unlocked map cannot save without finalization');
    tripCheck($conn->query("SELECT * FROM routes WHERE id=$id")->fetch_assoc()===$beforeUnlock,'Rejected unlocked save preserves every stored route value');
    tripCheck($conn->query("SELECT latitude,longitude,location_type,sequence FROM route_locations WHERE route_id=$id ORDER BY sequence")->fetch_all(MYSQLI_ASSOC)===$pins,'Rejected unlocked save preserves saved points');
    tripCheck(routePost(array_replace($edit,['trip_type'=>'round_trip']))['status']==='error','Changed trip type requires finalization');
    $round=array_replace($map,['trip_type'=>'round_trip','return_distance'=>40,'final_distance'=>70]);
    tripCheck(routePost(array_replace($edit,['trip_type'=>'round_trip','route_map_dirty'=>'1','route_data'=>json_encode($round)]))['status']==='success','Changed type can save after round-trip finalization');
    $row=$conn->query("SELECT distance_km,fuel_allocation,trip_type FROM routes WHERE id=$id")->fetch_assoc();
    tripCheck($row['distance_km']==='70.00' && $row['fuel_allocation']==='3.53' && $row['trip_type']==='round_trip','Round-trip existing formula and rounding');
    $oldMap=$round;unset($oldMap['trip_type']);
    tripCheck(routePost(array_replace($base,['origin'=>'New default round origin','destination'=>'New default round destination','route_data'=>json_encode($oldMap)]))['status']==='success','New route defaults to Round Trip with legacy payload');
    $default=$conn->query("SELECT trip_type,distance_km FROM routes WHERE origin='New default round origin'")->fetch_assoc();
    tripCheck($default['trip_type']==='round_trip' && $default['distance_km']==='70.00','Default new Round Trip distance preserved');
    foreach(['round_trip','one_way'] as $type){
        $fixed=array_replace($base,['origin'=>'Fixed '.$type,'destination'=>'Fixed destination '.$type,'is_fixed_fuel'=>'1','fuel_allocation'=>'7.50','trip_type'=>$type]);
        tripCheck(routePost($fixed)['status']==='success','Fixed Fuel save '.$type);
        $row=$conn->query("SELECT * FROM routes WHERE origin='Fixed $type'")->fetch_assoc();
        tripCheck($row['distance_km']==='0.10' && $row['fuel_allocation']==='7.50','Fixed Fuel stays unchanged '.$type);
    }
    $legacyEdit=array_replace($edit,['routes_id'=>1,'origin'=>'Legacy origin','destination'=>'Legacy destination','fuel_allocation'=>'6.78']);unset($legacyEdit['trip_type']);
    tripCheck(routePost($legacyEdit)['status']==='success','Legacy client edit defaults to stored Round Trip');
    tripCheck($conn->query('SELECT distance_km FROM routes WHERE id=1')->fetch_row()[0]==='123.45','Legacy distance unchanged');
    foreach([['trip_type'=>'invalid'],['trip_type'=>'one_way','route_data'=>json_encode($round)],['trip_type'=>'one_way','route_data'=>json_encode(array_replace($map,['final_distance'=>70]))]] as $bad) tripCheck(routePost(array_replace($base,$bad))['status']==='error','Invalid/mismatched trip data rejected');
    echo "$checks route trip-type database/HTTP checks passed.\n";
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(isset($conn))$conn->close();$source->query("DROP DATABASE `$db`");$source->close();
    if(is_dir($sandbox)){
        $resolved=realpath($sandbox);$temp=realpath(sys_get_temp_dir());
        if(!$resolved||!str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR.'gslip_trip_test_'))throw new RuntimeException('Unsafe cleanup');
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($resolved);
    }
}
