<?php
// Presentation only. Call before HTML escaping or CSV serialization, never on input.
function formatRequestedBy(?string $value): string
{
    return mb_strtoupper($value ?? '', 'UTF-8');
}

function formatListingProperCase(?string $value): string
{
    $first = true;
    $connectors = ['at','of','in','on','to','for','and','the'];
    $acronyms = ['ICT','GPS','PALECO','GSLIP','ASOD','ANOD','ISD','NGCP','NPC','DOE','DENR','DPWH','LGU','GIS','CCTV','IT','SCADA'];
    return preg_replace_callback('/[\p{L}]{1,8}[-\/]\d[\p{L}\p{N}\/-]*|[\p{L}\p{M}\p{N}]+/u',
        static function (array $match) use (&$first, $connectors, $acronyms): string {
            $word = $match[0];
            $upper = mb_strtoupper($word, 'UTF-8');
            $lower = mb_strtolower($word, 'UTF-8');
            $initial = $first;
            if (preg_match('/\p{L}/u', $word)) $first = false;
            // Keep identifiers such as R-001, 13KV and AB12 exactly as entered.
            if (preg_match('/\p{N}/u', $word)) return $word;
            if (in_array($upper, $acronyms, true)) return $upper;
            if (!$initial && in_array($lower, $connectors, true)) return $lower;
            return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }, $value ?? '') ?? '';
}

function formatPurpose(?string $value): string
{
    return formatListingProperCase($value);
}

function formatRoute(?string $value): string
{
    return formatListingProperCase($value);
}
