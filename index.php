<?php
/*
Plugin Name: Automatically scrapes and clone posts
Description: Fetches data from an external resource every 10 minutes and creates posts in WordPress.
Version: 1.0
Author: acvid3
Email: acvid3@gmail.com
*/

require_once 'vendor/autoload.php';

use Google\Client;
use Google\Service\Oauth2;
use Google\Service\Translate;

add_action('my_custom_cron_event', 'fetch_and_create_posts');
function schedule_custom_cron() {
    if (!wp_next_scheduled('my_custom_cron_event')) {
        wp_schedule_event(time(), 'every_ten_minutes', 'my_custom_cron_event');
    }
}
add_action('wp', 'schedule_custom_cron');

function fetch_html_with_curl($url) {
    $curl = curl_init();
    $headers = array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36', // Example User-Agent header
    );
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers,
    ));

    $response = curl_exec($curl);
    $error = curl_error($curl);

    if ($error) {
        error_log("cURL Error: $error");
        return false;
    }

    curl_close($curl);

    $dom = new DOMDocument();
    @$dom->loadHTML($response);

    return new DOMXPath($dom);
}

function fetch_and_parse_html($url) {
    $xpath = fetch_html_with_curl($url);

    $posts_url = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' post-card-inline__figure-link ')]/@href");

    $link_element = $posts_url[0];

    $sub_xpath = fetch_html_with_curl('https://cointelegraph.com/' . $link_element->nodeValue);

    $title_elements = $sub_xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' post__title ')]");
    $title = '';
    foreach ($title_elements as $element) {
        $title = $element->nodeValue;
        break;
    }

    $content_elements = $sub_xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' post-content ')]");
    $content = '';
    foreach ($content_elements as $element) {
        $content .= $element->nodeValue . "\n";
    }

    $rout = $link_element->nodeValue;
    $category = '/news/';
    $slug = str_replace($category, '', $rout);

    return array(
        $slug => array(
            'title' => $title,
            'content' => $content
        )
    );
}

function google_translate($text, $sourceLang, $targetLang, $apiKey) {
    $url = "https://translation.googleapis.com/language/translate/v2";

    $fields = [
        'q' => $text,
        'source' => $sourceLang,
        'target' => $targetLang,
        'format' => 'text'
    ];

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ];
    

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);

    return json_decode($response, true);
}

function is_existing_posts($key, $url) {
    $query = array(
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => array(
            'relation'     => 'AND',
            array(
                'key'      => $key,
                'value'    => $url,
                'compare'  => '=',
            )
        ),
    );
    $posts = get_posts($query);

    return $posts;
}

function fetch_and_create_posts() {
    $data = fetch_and_parse_html('https://cointelegraph.com/tags/bitcoin');  
    $access_token = getAccessToken(__DIR__ . '/service-account.json');  

    foreach ($data as $slug => $post) {
        $parameters = array(
            'en' => array(
                'title' => $post['title'],
                'content' => $post['content']
            ),
            'uk' => array(
                'title' => google_translate($post['title'], 'en', 'uk', $access_token)['data']['translations'][0]['translatedText'],
                'content' => google_translate($post['content'], 'en', 'uk', $access_token)['data']['translations'][0]['translatedText']
            ),
            'ru' => array(
                'title' => google_translate($post['title'], 'en', 'ru', $access_token)['data']['translations'][0]['translatedText'],
                'content' => google_translate($post['content'], 'en', 'ru', $access_token)['data']['translations'][0]['translatedText']
            )
        );

        $resp = google_translate($post['title'], 'en', 'uk', $access_token);

        $translations = array();

        foreach ($parameters as $key_language => $data) {
            $post_data = array(
                'post_title'    => $data['title'],
                'post_content'  => $data['content'],
                'post_status'   => 'draft',
                'post_author'   => 1,
                'post_type'     => 'post',
            );

            $existing_posts = is_existing_posts("existing_post_$key_language", "language_code_$key_language-$slug");
            if (!$existing_posts) {
                $post_id = wp_insert_post( $post_data );

                update_post_meta($post_id, "existing_post_$key_language", "language_code_$key_language-$slug");

                if (is_wp_error($post_id)) {
                    return new WP_Error('create_post_error', 'Failed to create post', array('status' => 500));
                }

                $translations[$key_language] = $post_id;
                pll_set_post_language($post_id, $key_language);
                pll_save_post_translations( $translations );
            }
        }
    }
}

function getAccessToken($credentialsPath) {
    $client = new Client();
    $client->setAuthConfig($credentialsPath);
    $client->addScope(Translate::CLOUD_TRANSLATION);
    $client->fetchAccessTokenWithAssertion();
    $token = $client->getAccessToken();
    return $token['access_token'] ?? '';
}