<?php
// Function to store project data
function nmkr_store_project_exact($project_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_projects';
    
    // Start performance tracking
    $tracking = nmkr_start_performance_tracking('store_project');
    
    // Validate required fields before processing
    if (!is_array($project_data)) {
        nmkr_log_data_sync('Invalid project data: expected array, got ' . gettype($project_data), 'error');
        nmkr_end_performance_tracking($tracking);
        return new WP_Error('nmkr_project_validation_failed', 'Project data is invalid.');
    }
    
    // Validate critical required fields
    $required_fields = ['uid', 'projectname'];
    $missing_fields = array();
    
    foreach ($required_fields as $field) {
        if (!isset($project_data[$field]) || $project_data[$field] === null || $project_data[$field] === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        nmkr_log_data_sync('Project data missing required fields: ' . implode(', ', $missing_fields), 'error', array(
            'project_data_keys' => array_keys($project_data)
        ));
        nmkr_end_performance_tracking($tracking);
        return new WP_Error('nmkr_project_validation_failed', 'Project data is missing required fields.');
    }
    
    // Create a unique hash for the project data
    $hash = nmkr_generate_project_hash($project_data);
    
    // Prepare data for insert/update
    $data = array(
        'project_id' => isset($project_data['id']) ? sanitize_text_field($project_data['id']) : '',
        'project_uid' => sanitize_text_field($project_data['uid']),
        'project_name' => sanitize_text_field($project_data['projectname']),
        'project_url' => isset($project_data['projecturl']) ? sanitize_text_field($project_data['projecturl']) : '',
        'project_logo' => isset($project_data['projectLogo']) ? sanitize_text_field($project_data['projectLogo']) : '',
        'state' => isset($project_data['state']) ? sanitize_text_field($project_data['state']) : '',
        'free' => isset($project_data['free']) ? intval($project_data['free']) : 0,
        'sold' => isset($project_data['sold']) ? intval($project_data['sold']) : 0,
        'reserved' => isset($project_data['reserved']) ? intval($project_data['reserved']) : 0,
        'total' => isset($project_data['total']) ? intval($project_data['total']) : 0,
        'blocked' => isset($project_data['blocked']) ? intval($project_data['blocked']) : 0,
        'total_blocked' => isset($project_data['totalBlocked']) ? intval($project_data['totalBlocked']) : 0,
        'total_tokens' => isset($project_data['totalTokens']) ? intval($project_data['totalTokens']) : 0,
        'error' => isset($project_data['error']) ? intval($project_data['error']) : 0,
        'unknown_or_burned_state' => isset($project_data['unknownOrBurnedState']) ? intval($project_data['unknownOrBurnedState']) : 0,
        'max_token_supply' => isset($project_data['maxTokenSupply']) ? intval($project_data['maxTokenSupply']) : 0,
        'description' => isset($project_data['description']) ? sanitize_textarea_field($project_data['description']) : '',
        'address_reservation_time' => isset($project_data['addressReservationTime']) ? intval($project_data['addressReservationTime']) : 0,
        'policy_id' => isset($project_data['policyId']) ? sanitize_text_field($project_data['policyId']) : '',
        'enable_cross_sale_on_payment_gateway' => isset($project_data['enableCrossSaleOnPaymentGateway']) ? intval($project_data['enableCrossSaleOnPaymentGateway']) : 0,
        'ada_payout_wallet_address' => isset($project_data['adaPayoutWalletAddress']) ? sanitize_text_field($project_data['adaPayoutWalletAddress']) : '',
        'usdc_payout_wallet_address' => isset($project_data['usdcPayoutWalletAddress']) ? sanitize_text_field($project_data['usdcPayoutWalletAddress']) : '',
        'enable_fiat_payments' => isset($project_data['enableFiatPayments']) ? intval($project_data['enableFiatPayments']) : 0,
        'payment_gateway_sale_start' => isset($project_data['paymentGatewaySaleStart']) ? sanitize_text_field($project_data['paymentGatewaySaleStart']) : '',
        'enable_decentral_payments' => isset($project_data['enableDecentralPayments']) ? intval($project_data['enableDecentralPayments']) : 0,
        'policy_locks' => isset($project_data['policyLocks']) ? sanitize_text_field($project_data['policyLocks']) : '',
        'royalty_address' => isset($project_data['royaltyAddress']) ? sanitize_text_field($project_data['royaltyAddress']) : '',
        'royalty_percent' => isset($project_data['royaltyPercent']) ? floatval($project_data['royaltyPercent']) : null,
        'lockslot' => isset($project_data['lockslot']) ? intval($project_data['lockslot']) : null,
        'disable_manual_mintingbutton' => isset($project_data['disableManualMintingbutton']) ? intval($project_data['disableManualMintingbutton']) : 0,
        'disable_random_sales' => isset($project_data['disableRandomSales']) ? intval($project_data['disableRandomSales']) : 0,
        'disable_specific_sales' => isset($project_data['disableSpecificSales']) ? intval($project_data['disableSpecificSales']) : 0,
        'twitter_handle' => isset($project_data['twitterHandle']) ? sanitize_text_field($project_data['twitterHandle']) : '',
        'nmkr_account_options' => isset($project_data['nmkrAccountOptions']) ? sanitize_text_field($project_data['nmkrAccountOptions']) : '',
        'crossmint_collection_id' => isset($project_data['crossmintCollectiondId']) ? sanitize_text_field($project_data['crossmintCollectiondId']) : '',
        'blockchain' => isset($project_data['blockchains']) && is_array($project_data['blockchains']) ? sanitize_text_field($project_data['blockchains'][0] ?? '') : '',
        'solana_project_details' => isset($project_data['solanaProjectDetails']) ? sanitize_textarea_field($project_data['solanaProjectDetails']) : '',
        'hash' => $hash
    );
    
    // Check if project already exists
    $existing_project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}nmkr_projects WHERE project_uid = %s",
        $project_data['uid']
    ));
    if ($wpdb->last_error !== '') {
        nmkr_end_performance_tracking($tracking);
        return new WP_Error('nmkr_project_existence_query_failed', 'Project existence check failed.');
    }
    
    if ($existing_project) {
        // Update existing project if hash is different
        if ($existing_project->hash !== $hash) {
            // Only update updated_at if data actually changed
            $data['updated_at'] = nmkr_get_timestamp();
            $data['synced_at'] = nmkr_get_timestamp();
            
            $write_result = $wpdb->update(
                "{$wpdb->prefix}nmkr_projects",
                $data,
                array('project_uid' => $project_data['uid'])
            );
            
            if ($write_result === false) { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_project_write_failed', 'Project update failed.'); }
            $action = $write_result === 0 ? 'unchanged' : 'updated';
            nmkr_log_data_sync('Updated project with changes', 'debug', array(
                'project_uid' => $project_data['uid'],
                'project_name' => $project_data['projectname']
            ));
        } else {
            // Only update synced_at if data hasn't changed
            $write_result = $wpdb->update(
                "{$wpdb->prefix}nmkr_projects",
                array('synced_at' => nmkr_get_timestamp()),
                array('project_uid' => $project_data['uid'])
            );
            
            if ($write_result === false) { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_project_write_failed', 'Project update failed.'); }
            $action = 'unchanged';
            nmkr_log_data_sync('Project unchanged, only updated synced_at', 'debug', array(
                'project_uid' => $project_data['uid'],
                'project_name' => $project_data['projectname']
            ));
        }
    } else {
        // Add created_at for new projects
        $data['created_at'] = nmkr_get_timestamp();
        $data['updated_at'] = nmkr_get_timestamp();
        $data['synced_at'] = nmkr_get_timestamp();
        
        // Insert new project
        $write_result = $wpdb->insert(
            "{$wpdb->prefix}nmkr_projects",
            $data
        );
        
        if ($write_result === false) { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_project_write_failed', 'Project insert failed.'); }
        $action = 'inserted';
        nmkr_log_data_sync('Created new project', 'info', array(
            'project_uid' => $project_data['uid'],
            'project_name' => $project_data['projectname']
        ));
    }
    
    // End performance tracking
    $performance = nmkr_end_performance_tracking($tracking);
    nmkr_record_database_operation('project', $performance['duration']);
    
    return array('action' => $action);
}

// Function to store token data
function nmkr_store_token_exact($token_data, $project_uid) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'nmkr_tokens';
    
    // Start performance tracking
    $tracking = nmkr_start_performance_tracking('store_token');
    
    // Validate required fields before processing
    if (!is_array($token_data)) {
        nmkr_log_data_sync('Invalid token data: expected array, got ' . gettype($token_data), 'error');
        nmkr_end_performance_tracking($tracking);
        return new WP_Error('nmkr_token_validation_failed', 'Token data is invalid.');
    }
    
    // Validate critical required fields
    $required_fields = ['uid', 'id', 'name'];
    $missing_fields = array();
    
    foreach ($required_fields as $field) {
        if (!isset($token_data[$field]) || $token_data[$field] === null || $token_data[$field] === '') {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        nmkr_log_data_sync('Token data missing required fields: ' . implode(', ', $missing_fields), 'error', array(
            'token_data_keys' => array_keys($token_data),
            'project_uid' => $project_uid
        ));
        nmkr_end_performance_tracking($tracking);
        return new WP_Error('nmkr_token_validation_failed', 'Token data is missing required fields.');
    }
    
    // Generate hash for the token data
    $hash = nmkr_generate_token_hash($token_data);
    
    // Check if token already exists
    $existing_token = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE token_uid = %s",
        $token_data['uid']
    ));
    if ($wpdb->last_error !== '') { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_token_existence_query_failed', 'Token existence check failed.'); }
    
    if ($existing_token) {
        // Update existing token if hash is different
        if ($existing_token->hash !== $hash) {
            $write_result = $wpdb->update(
                $table_name,
                array(
                    'token_name' => $token_data['name'],
                    'display_name' => isset($token_data['displayName']) ? $token_data['displayName'] : '',
                    'detail_data' => isset($token_data['detailData']) ? $token_data['detailData'] : '',
                    'ipfs_link' => isset($token_data['ipfsLink']) ? $token_data['ipfsLink'] : '',
                    'gateway_link' => isset($token_data['gatewayLink']) ? $token_data['gatewayLink'] : '',
                    'state' => isset($token_data['state']) ? $token_data['state'] : '',
                    'minted' => isset($token_data['minted']) ? $token_data['minted'] : 0,
                    'policy_id' => isset($token_data['policyId']) ? $token_data['policyId'] : '',
                    'asset_id' => isset($token_data['assetId']) ? $token_data['assetId'] : '',
                    'asset_name' => isset($token_data['assetName']) ? $token_data['assetName'] : '',
                    'fingerprint' => isset($token_data['fingerprint']) ? $token_data['fingerprint'] : '',
                    'initial_mint_tx_hash' => isset($token_data['initialMintTxHash']) ? $token_data['initialMintTxHash'] : '',
                    'series' => isset($token_data['series']) ? $token_data['series'] : '',
                    'token_amount' => isset($token_data['tokenAmount']) ? $token_data['tokenAmount'] : 0,
                    'price' => isset($token_data['price']) ? $token_data['price'] : 0,
                    'price_solana' => isset($token_data['priceSolana']) ? $token_data['priceSolana'] : null,
                    'updated_at' => nmkr_get_timestamp(),
                    'synced_at' => nmkr_get_timestamp(),
                    'hash' => $hash
                ),
                array('token_uid' => $token_data['uid'])
            );
            $action = $write_result === 0 ? 'unchanged' : 'updated';
        } else {
            // Update synced_at even if data hasn't changed
            $write_result = $wpdb->update(
                $table_name,
                array('synced_at' => nmkr_get_timestamp()),
                array('token_uid' => $token_data['uid'])
            );
            $action = 'unchanged';
        }
    } else {
        // Insert new token
        $write_result = $wpdb->insert(
            $table_name,
            array(
                'token_id' => $token_data['id'],
                'token_uid' => $token_data['uid'],
                'project_uid' => $project_uid,
                'token_name' => $token_data['name'],
                'display_name' => isset($token_data['displayName']) ? $token_data['displayName'] : '',
                'detail_data' => isset($token_data['detailData']) ? $token_data['detailData'] : '',
                'ipfs_link' => isset($token_data['ipfsLink']) ? $token_data['ipfsLink'] : '',
                'gateway_link' => isset($token_data['gatewayLink']) ? $token_data['gatewayLink'] : '',
                'state' => isset($token_data['state']) ? $token_data['state'] : '',
                'minted' => isset($token_data['minted']) ? $token_data['minted'] : 0,
                'policy_id' => isset($token_data['policyId']) ? $token_data['policyId'] : '',
                'asset_id' => isset($token_data['assetId']) ? $token_data['assetId'] : '',
                'asset_name' => isset($token_data['assetName']) ? $token_data['assetName'] : '',
                'fingerprint' => isset($token_data['fingerprint']) ? $token_data['fingerprint'] : '',
                'initial_mint_tx_hash' => isset($token_data['initialMintTxHash']) ? $token_data['initialMintTxHash'] : '',
                'series' => isset($token_data['series']) ? $token_data['series'] : '',
                'token_amount' => isset($token_data['tokenAmount']) ? $token_data['tokenAmount'] : 0,
                'price' => isset($token_data['price']) ? $token_data['price'] : 0,
                'price_solana' => isset($token_data['priceSolana']) ? $token_data['priceSolana'] : null,
                'created_at' => nmkr_get_timestamp(),
                'updated_at' => nmkr_get_timestamp(),
                'synced_at' => nmkr_get_timestamp(),
                'hash' => $hash
            )
        );
        $action = 'inserted';
    }
    if ($write_result === false) { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_token_write_failed', 'Token write failed.'); }
    
    // End performance tracking
    $performance = nmkr_end_performance_tracking($tracking);
    nmkr_record_database_operation('token', $performance['duration']);
    
    // Update sync heartbeat to indicate backend activity
    nmkr_update_sync_heartbeat();
    
    return array('action' => $action);
}

// Storing the token details data
function nmkr_store_token_details_exact($token_uid, $token_details) {
    global $wpdb;
    $tracking = nmkr_start_performance_tracking('store_token_details');
    if ($token_uid === '' || !is_array($token_details)) { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_token_details_validation_failed', 'Token details are invalid.'); }
    
    // Create a unique hash for the token details
    $hash = nmkr_generate_token_details_hash($token_details);
    
    // Check if token already exists in database
    $token_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}nmkr_token_details WHERE token_uid = %s",
        $token_uid
    ));
    if ($wpdb->last_error !== '') { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_token_details_existence_query_failed', 'Token details existence check failed.'); }
    
    // Determine action based on existence
    if ($token_exists) {
        // Update existing token
        $write_result = $wpdb->update(
            "{$wpdb->prefix}nmkr_token_details",
            array(
                'receiver_address' => isset($token_details['receiveraddress']) ? sanitize_text_field($token_details['receiveraddress']) : null,
                'sell_date' => isset($token_details['selldate']) ? sanitize_text_field($token_details['selldate']) : null,
                'sold_by' => isset($token_details['soldby']) ? sanitize_text_field($token_details['soldby']) : null,
                'reserved_until' => isset($token_details['reserveduntil']) ? sanitize_text_field($token_details['reserveduntil']) : null,
                'title' => isset($token_details['title']) ? sanitize_text_field($token_details['title']) : null,
                'metadata' => isset($token_details['metadata']) ? sanitize_textarea_field($token_details['metadata']) : null,
                'payment_gateway_link' => isset($token_details['paymentGatewayLinkForSpecificSale']) ? html_entity_decode($token_details['paymentGatewayLinkForSpecificSale']) : null,
                'send_back_central_payment_lovelace' => isset($token_details['sendBackCentralPaymentInLovelace']) ? intval($token_details['sendBackCentralPaymentInLovelace']) : null,
                'send_back_central_payment_lamport' => isset($token_details['sendBackCentralPaymentInLamport']) ? intval($token_details['sendBackCentralPaymentInLamport']) : null,
                'price_lovelace_central' => isset($token_details['priceInLovelaceCentralPayments']) ? intval($token_details['priceInLovelaceCentralPayments']) : null,
                'upload_source' => isset($token_details['uploadSource']) ? sanitize_text_field($token_details['uploadSource']) : null,
                'price_lamport_central' => isset($token_details['priceInLamportCentralPayments']) ? intval($token_details['priceInLamportCentralPayments']) : null,
                'updated_at' => nmkr_get_timestamp(),
                'synced_at' => nmkr_get_timestamp(),
                'hash' => $hash
            ),
            array('token_uid' => $token_uid)
        );
        $action = $write_result === 0 ? 'unchanged' : 'updated';
    } else {
        // Insert new token
        $write_result = $wpdb->insert("{$wpdb->prefix}nmkr_token_details", array(
            'token_uid' => sanitize_text_field($token_uid),
            'receiver_address' => isset($token_details['receiveraddress']) ? sanitize_text_field($token_details['receiveraddress']) : null,
            'sell_date' => isset($token_details['selldate']) ? sanitize_text_field($token_details['selldate']) : null,
            'sold_by' => isset($token_details['soldby']) ? sanitize_text_field($token_details['soldby']) : null,
            'reserved_until' => isset($token_details['reserveduntil']) ? sanitize_text_field($token_details['reserveduntil']) : null,
            'title' => isset($token_details['title']) ? sanitize_text_field($token_details['title']) : null,
            'metadata' => isset($token_details['metadata']) ? sanitize_textarea_field($token_details['metadata']) : null,
            'payment_gateway_link' => isset($token_details['paymentGatewayLinkForSpecificSale']) ? html_entity_decode($token_details['paymentGatewayLinkForSpecificSale']) : null,
            'send_back_central_payment_lovelace' => isset($token_details['sendBackCentralPaymentInLovelace']) ? intval($token_details['sendBackCentralPaymentInLovelace']) : null,
            'send_back_central_payment_lamport' => isset($token_details['sendBackCentralPaymentInLamport']) ? intval($token_details['sendBackCentralPaymentInLamport']) : null,
            'price_lovelace_central' => isset($token_details['priceInLovelaceCentralPayments']) ? intval($token_details['priceInLovelaceCentralPayments']) : null,
            'upload_source' => isset($token_details['uploadSource']) ? sanitize_text_field($token_details['uploadSource']) : null,
            'price_lamport_central' => isset($token_details['priceInLamportCentralPayments']) ? intval($token_details['priceInLamportCentralPayments']) : null,
            'created_at' => nmkr_get_timestamp(),
            'updated_at' => nmkr_get_timestamp(),
            'synced_at' => nmkr_get_timestamp(),
            'hash' => $hash
        ));
        $action = 'inserted';
    }
    if ($write_result === false) { nmkr_end_performance_tracking($tracking); return new WP_Error('nmkr_token_details_write_failed', 'Token details write failed.'); }

    // End performance tracking
    $performance = nmkr_end_performance_tracking($tracking);
    nmkr_record_database_operation('token_details', $performance['duration']);
    
    // Update sync heartbeat to indicate backend activity
    nmkr_update_sync_heartbeat();
    
    return array('action' => $action);
}

// Boolean compatibility wrappers retained for external callers.
function nmkr_store_project($project_data) { return !is_wp_error(nmkr_store_project_exact($project_data)); }
function nmkr_store_token($token_data, $project_uid) { return !is_wp_error(nmkr_store_token_exact($token_data, $project_uid)); }
function nmkr_store_token_details($token_uid, $token_details) { return !is_wp_error(nmkr_store_token_details_exact($token_uid, $token_details)); }
