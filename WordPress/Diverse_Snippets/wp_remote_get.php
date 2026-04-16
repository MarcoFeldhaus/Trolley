$response = wp_remote_get('https://backend.mycity.cards/api/v1/partners?region_name=' . $region_name, array(
          'headers' => array(
               'Accept' => 'application/json',
               'X-API-KEY' => get_white_label_region_api_key()
          )
     ));