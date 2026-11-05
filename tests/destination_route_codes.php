<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli('localhost','root','');$db='gslip_destination_test_'.bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `$db`");$conn->select_db($db);
$sandbox=sys_get_temp_dir().'/gslip_destination_test_'.bin2hex(random_bytes(6));$server=null;$checks=0;
function destinationCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);++$checks;}
function destinationRequest($params){global $address;return json_decode(file_get_contents('http://'.$address.'/get_destinations.php?'.http_build_query($params)),true,512,JSON_THROW_ON_ERROR);}
try{
 $conn->query('CREATE TABLE areas(id INT PRIMARY KEY,area_name VARCHAR(100))');
 $conn->query("INSERT INTO areas VALUES(1,'Test'),(2,'Other')");
 $conn->query('CREATE TABLE routes(id INT PRIMARY KEY,route CHAR(6),destination VARCHAR(100),area VARCHAR(100),distance_km DECIMAL(6,2),fuel_allocation DECIMAL(6,2),is_fixed_fuel INT,status VARCHAR(20))');
 $conn->query("INSERT INTO routes VALUES(1,'PX10','Bapa','Test',30,2,0,'active'),(2,'PX2','Bapa','Test',12.75,1,0,'active'),(3,NULL,'Uncoded','Test',8.50,NULL,0,'active'),(4,'','Fixed','Test',0.10,7.50,1,'active'),(5,'PX3','Coded fixed','Test',0.10,6,1,'active'),(6,'PX4','Inactive','Test',5,1,0,'inactive'),(7,'PX1','Other area','Other',5,1,0,'active'),(8,NULL,'Missing distance','Test',NULL,NULL,0,'active')");
 mkdir($sandbox.'/includes',0777,true);copy(__DIR__.'/../get_destinations.php',$sandbox.'/get_destinations.php');
 file_put_contents($sandbox.'/includes/config.php','<?php function getDBConnection(){return new mysqli("localhost","root","",'.var_export($db,true).');}');
 file_put_contents($sandbox.'/router.php','<?php session_save_path(__DIR__);session_start();$_SESSION=["area"=>1,"user_id"=>9];session_write_close();return false;');
 $socket=stream_socket_server('tcp://127.0.0.1:0');$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-S',$address,'-t',$sandbox,$sandbox.'/router.php'],[0=>['pipe','r'],1=>['file',$sandbox.'/server.log','a'],2=>['file',$sandbox.'/server.log','a']],$pipes);fclose($pipes[0]);
 for($i=0;$i<30;$i++){$socket=@stream_socket_client('tcp://'.$address,$errno,$errstr,.1);if($socket){fclose($socket);break;}usleep(100000);}
 foreach([[1,'2-wheels',[1,2,5]],[0,'2-wheels',[4,5]],[0,'4-wheels',[3,4,8]],[0,'trucks',[3,4,8]]] as [$approved,$category,$expected]){
  $data=destinationRequest(['useRoutes'=>$approved,'vehicleCategory'=>$category]);$ids=array_column($data['destinations'],'route_id');sort($ids);
  destinationCheck($ids===$expected,'Existing approved/fixed/vehicle/area/status filtering: '.$category.'/'.$approved);
  foreach($data['destinations'] as $row){
   $stored=$conn->query('SELECT * FROM routes WHERE id='.(int)$row['route_id'])->fetch_assoc();
   destinationCheck($row['route_code']===$stored['route'] && $row['name']===$stored['destination'],'Actual route code and original destination');
   destinationCheck((float)$row['km']===(float)$stored['distance_km'] && (float)$row['fuel_allocation']===(float)$stored['fuel_allocation'],'Distance/fuel values unchanged');
  }
 }
 $data=destinationRequest(['useRoutes'=>1,'vehicleCategory'=>'2-wheels','selected_route_ids'=>'1,6,7,1 OR 1=1']);
 destinationCheck(count($data['destinations'])===3,'Metadata does not change available filter results');
 destinationCheck(($data['route_codes'][1]??null)==='PX10' && ($data['route_codes'][6]??null)==='PX4','Saved selection codes returned even outside active filter');
 destinationCheck(!isset($data['route_codes'][7]),'Selected metadata retains original area restriction');
 destinationCheck(count(array_filter($data['destinations'],fn($d)=>$d['name']==='Bapa'))===2,'Duplicate destination names preserve distinct route records');
 // Exercise the real old-template loader and save/reload workflow, not a
 // manually normalized frontend approximation of the template response.
 $conn->query('CREATE TABLE templates(id INT AUTO_INCREMENT PRIMARY KEY,template_name VARCHAR(100),created_by INT,created_at DATETIME,status VARCHAR(20))');
 $conn->query('CREATE TABLE template_rows(id INT AUTO_INCREMENT PRIMARY KEY,template_id INT,vehicle_id INT,purpose VARCHAR(100),requested_by VARCHAR(100),row_order INT)');
 $conn->query('CREATE TABLE vehicles(id INT PRIMARY KEY,plate_no VARCHAR(20),brand VARCHAR(20),model VARCHAR(20),category VARCHAR(20),km_per_liter DECIMAL(6,2))');
 $conn->query('CREATE TABLE templates_routes(id INT AUTO_INCREMENT PRIMARY KEY,template_id INT,route_id INT,route_order INT,row_no INT,row_id INT)');
 $conn->query('CREATE TABLE templates_fuel(id INT AUTO_INCREMENT PRIMARY KEY,template_id INT,fuel_item_id INT,quantity DECIMAL(6,2),container VARCHAR(10),row_no INT,row_id INT)');
 $conn->query("INSERT INTO vehicles VALUES(1,'TEST','Brand','Model','2-wheels',30)");
 $conn->query("INSERT INTO templates VALUES(100,'Old template',9,NOW(),'active'),(101,'Other owner',10,NOW(),'active')");
 $conn->query("INSERT INTO template_rows VALUES(200,100,1,'First purpose','First requester',1),(201,100,1,'Second purpose','Second requester',2)");
 $conn->query('INSERT INTO templates_routes(template_id,route_id,route_order,row_no,row_id) VALUES(100,1,1,1,200),(100,2,2,1,200),(100,3,1,2,201),(100,5,2,2,201)');
 foreach(['load_template.php','save_template.php'] as $file)copy(__DIR__.'/../'.$file,$sandbox.'/'.$file);
 $beforeLinks=$conn->query('SELECT * FROM templates_routes ORDER BY id')->fetch_all(MYSQLI_ASSOC);
 $template=json_decode(file_get_contents('http://'.$address.'/load_template.php?id=100'),true,512,JSON_THROW_ON_ERROR);
 destinationCheck($template['success'] && count($template['rows'])===2,'Older multi-row template loads without resaving');
 $first=$template['rows'][0]['destinations'];
 destinationCheck(array_column($first,'route_id')===[1,2] && array_column($first,'route_code')===['PX10','PX2'],'Actual IDs and stored route order resolve distinct codes for duplicate names');
 destinationCheck((float)$first[1]['km']===12.75 && (float)$first[1]['fuel_allocation']===1.0,'Template metadata uses original route distances and fuel values');
 destinationCheck($template['rows'][1]['destinations'][0]['route_code']===null && $template['rows'][1]['destinations'][0]['name']==='Uncoded','Uncoded template destination retains actual name');
 destinationCheck($conn->query('SELECT * FROM templates_routes ORDER BY id')->fetch_all(MYSQLI_ASSOC)===$beforeLinks,'Loading does not change stored template contents');
 $other=json_decode(file_get_contents('http://'.$address.'/load_template.php?id=101'),true);
 destinationCheck(!$other['success'],'Existing template ownership restriction preserved');
 $payload=['name'=>'Newly saved template','data'=>array_map(function($row){$row['fuel_requests']=[];return $row;},$template['rows'])];
 $post=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json','content'=>json_encode($payload)]]);
 $saved=json_decode(file_get_contents('http://'.$address.'/save_template.php',false,$post),true);
 destinationCheck($saved['success'],'Existing save-template endpoint works unchanged');
 $newId=$conn->query("SELECT id FROM templates WHERE template_name='Newly saved template'")->fetch_row()[0];
 $reloaded=json_decode(file_get_contents('http://'.$address.'/load_template.php?id='.$newId),true);
 destinationCheck(array_column($reloaded['rows'][0]['destinations'],'route_code')===['PX10','PX2'],'Newly saved template resolves codes on reload');
 echo "$checks destination route-code database/HTTP checks passed.\n";
}finally{
 if(is_resource($server)){proc_terminate($server);proc_close($server);}
 $conn->query("DROP DATABASE `$db`");$conn->close();
 if(is_dir($sandbox)){
  $resolved=realpath($sandbox);$temp=realpath(sys_get_temp_dir());
  if(!$resolved||!str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR.'gslip_destination_test_'))throw new RuntimeException('Unsafe cleanup');
  $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($files as $file)$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());rmdir($resolved);
 }
}
