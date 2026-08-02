<?php
/** Normalize an API blockchain value into meaningful, ordered chain names. */
function nmkr_normalize_project_blockchains($value) {
    $values = is_array($value) ? $value : array($value);
    $normalized = array();
    $seen = array();
    foreach ($values as $chain) {
        if (!is_scalar($chain) || is_bool($chain)) continue;
        $chain = trim(sanitize_text_field((string) $chain));
        if ($chain === '') continue;
        $key = strtolower($chain);
        if ($key === 'cardano') $chain = 'Cardano';
        elseif ($key === 'solana') $chain = 'Solana';
        $key = strtolower($chain);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $normalized[] = $chain;
    }
    return $normalized;
}

/** Serialize the authoritative blockchain collection as a JSON array. */
function nmkr_serialize_project_blockchains($value) {
    $json = wp_json_encode(nmkr_normalize_project_blockchains($value));
    return is_string($json) ? $json : '[]';
}
