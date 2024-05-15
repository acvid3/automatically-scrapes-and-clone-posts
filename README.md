# Plugin Name: Automatically Scrapes and Clone Posts

## Description
This WordPress plugin automatically fetches data from an external resource every 10 minutes and creates posts on your WordPress site. It uses cURL to retrieve HTML from web pages and Google Translate API to translate content into different languages.

## Version
1.0

## Author
acvid3 (Email: acvid3@gmail.com)

## Requirements
- WordPress 5.0 or higher
- PHP 7.3 or higher
- Access to Google Cloud Translation API and a corresponding API key

## Installation
1. Download the plugin into the `/wp-content/plugins/` directory of your WordPress site.
2. Navigate to the WordPress admin panel and activate the plugin through the "Plugins" menu.
3. Ensure that WP Cron tasks are enabled in your `wp-config.php` file or set up a system cron to call `wp-cron.php` every 10 minutes.

## Configuration
To configure the plugin:
1. Set up Google Cloud Translation API and obtain an API key.
2. Create a `service-account.json` file with your Google API credentials and save it in the root directory of the plugin.

## Usage
After activation and configuration, the plugin operates automatically. Every 10 minutes, it will:
- Fetch HTML from an external resource.
- Parse the HTML to extract new post data.
- Create new posts based on the extracted data.
- Translate posts into Ukrainian and Russian using Google Translate.

## Plugin Functions
- `fetch_and_create_posts()`: The main function that is scheduled to run, handling the entire process of data fetching and post creation.
- `fetch_html_with_curl($url)`: Function for obtaining HTML from external sites.
- `fetch_and_parse_html($url)`: Function for parsing HTML and extracting data for posts.
- `google_translate($text, $sourceLang, $targetLang, $apiKey)`: Function for translating text using Google API.

## Important Information
- Ensure you have the appropriate rights to extract data from external websites.
- Adhering to data usage policies and copyright laws is crucial when extracting content.

## Support
For questions or issues with the plugin, contact the author at the provided email.
