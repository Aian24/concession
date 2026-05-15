<?php
/**
 * Utility Class to fetch Sales data from a Remote WordPress/WooCommerce server on Bluehost.
 */
class WooCommerceFetcher {
    
    private $wp_url;
    private $consumer_key;
    private $consumer_secret;

    public function __construct($wp_url, $consumer_key, $consumer_secret) {
        $this->wp_url = rtrim($wp_url, '/');
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
    }

    /**
     * Method 1: The Recommended Way (Using WooCommerce REST API)
     * Why? Bluehost often blocks remote database connections. Also, WooCommerce data 
     * is split across dozens of complex tables (and using HPOS), making direct SQL queries a nightmare.
     */
    public function fetchRecentSalesAPI($limit = 50, $page = 1, $status = 'any', $search = '', $start_date = '', $end_date = '') {
        // Construct the Endpoint
        $endpoint = "{$this->wp_url}/wp-json/wc/v3/orders?per_page={$limit}&page={$page}";
        if ($status !== 'any' && $status !== 'mine') {
            $endpoint .= "&status=" . urlencode($status);
        }
        if (!empty($search)) {
            $endpoint .= "&search=" . urlencode($search);
        }
        if (!empty($start_date)) {
            $endpoint .= "&after=" . urlencode($start_date . 'T00:00:00Z');
        }
        if (!empty($end_date)) {
            $endpoint .= "&before=" . urlencode($end_date . 'T23:59:59Z');
        }

        // Set up cURL to make an HTTP request to your Bluehost website
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Required to extract X-WP-Total
        
        // Basic Authentication using the WooCommerce Consumer Key/Secret
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->consumer_key}:{$this->consumer_secret}");
        
        // Timeout settings
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) return ['success' => false, 'error' => $error];
        if ($http_code !== 200) return ['success' => false, 'error' => "HTTP Error Code: $http_code"];

        $header_str = substr($response, 0, $header_size);
        $body_str = substr($response, $header_size);
        
        $total_items = 0;
        $total_pages = 1;
        
        if (preg_match('/x-wp-total: (\d+)/i', $header_str, $matches)) {
            $total_items = (int)$matches[1];
        }
        if (preg_match('/x-wp-totalpages: (\d+)/i', $header_str, $matches)) {
            $total_pages = (int)$matches[1];
        }

        $orders = json_decode($body_str, true) ?: [];
        
        // Process the orders into a cleaner format for our dashboard
        $formatted_sales = [];
        foreach ($orders as $order) {
            $customer_name = trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? ''));
            if (empty($customer_name)) {
                $customer_name = 'Guest / Unnamed';
            }

            $formatted_sales[] = [
                'id' => "#" . $order['id'],
                'customer' => $customer_name,
                'type' => 'Sale', // You can map order status to type
                'amount' => '₱' . number_format((float)$order['total'], 2),
                'date' => date('M d, Y', strtotime($order['date_created'])),
                'store' => 'RustyLopez',
                'status' => ucfirst($order['status'])
            ];
        }

        return [
            'success' => true, 
            'data' => $formatted_sales,
            'total_items' => $total_items,
            'total_pages' => $total_pages
        ];
    }

    /**
     * Gets the counts for each order status for the badge numbers.
     */
    public function fetchOrderTotals() {
        $endpoint = "{$this->wp_url}/wp-json/wc/v3/reports/orders/totals";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->consumer_key}:{$this->consumer_secret}");
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return [];
        $data = json_decode($response, true);
        
        $counts = ['any' => 0];
        if (is_array($data)) {
            foreach($data as $status) {
                $counts[$status['slug']] = $status['total'];
                $counts['any'] += $status['total'];
            }
        }
        return $counts;
    }

    /**
     * To use this, you MUST go to your Bluehost cPanel -> Remote MySQL, 
     * and add the IP address of WHERE THIS PHP CODE is running so it's not blocked.
     */
    public static function fetchSalesFromDB($db_host, $db_name, $db_user, $db_pass) {
        try {
            $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            /* 
             * Getting WooCommerce orders through pure SQL is very complex due to meta_keys or HPOS. 
             * This query ONLY works for older WooCommerce setups before HPOS was enforced.
             * (This is why Method 1 is significantly better).
             */
            $sql = "SELECT p.ID as order_id, p.post_date, p.post_status, 
                           MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as order_total
                    FROM wp_posts p
                    LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'shop_order' AND p.post_status IN ('wc-completed', 'wc-processing')
                    GROUP BY p.ID
                    ORDER BY p.post_date DESC
                    LIMIT 10";

            $stmt = $pdo->query($sql);
            return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

        } catch (PDOException $e) {
            // Usually prints: "SQLSTATE[HY000] [2002] Connection timed out" if Remote IP isn't whitelisted in Bluehost cPanel
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
