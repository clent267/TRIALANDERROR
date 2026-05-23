<?php

namespace app\controller;

use think\facade\Session;
use app\model\UserModel;
use app\model\GameModel;
use think\facade\Request;
use think\facade\View;

use Unirest\Request as UnirestRequest;
use Unirest\Request\Body as UnirestBody;

class Arkoselabs
{
    // Configuration
    private $use_proxy = true;
    private $proxy_list = [
        "socks5://proxy1.example.com:1080",
        "http://proxy2.example.com:8080",
    ];
    
    private $rate_limit_config = [
        'enabled' => false, // Disabled for Render
        'requests_per_minute' => 9999,
        'cache_key_prefix' => 'fc_rl_'
    ];
    
    private $cache_dir = '/tmp/fc_cache';

    public function __construct() {
        @mkdir($this->cache_dir, 0777, true);
        UnirestRequest::timeout(30);
    }

    private function secure_encrypt($data, $key) {
        $iv = openssl_random_pseudo_bytes(16);
        $tag = '';
        $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, 0, $iv, $tag);
        if ($encrypted === false) {
            return false;
        }
        return base64_encode($iv . $tag . $encrypted);
    }
    
    private function secure_decrypt($data, $key) {
        try {
            $data = base64_decode($data);
            $iv = substr($data, 0, 16);
            $tag = substr($data, 16, 16);
            $encrypted = substr($data, 32);
            $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, 0, $iv, $tag);
            if ($decrypted === false) {
                return false;
            }
            return $decrypted;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getRandomProxy() {
        if (empty($this->proxy_list)) {
            return null;
        }
        return $this->proxy_list[array_rand($this->proxy_list)];
    }

    private function parseProxy($proxy) {
        $parsed = parse_url($proxy);
        return [
            'scheme' => $parsed['scheme'] ?? 'http',
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? ($parsed['scheme'] === 'socks5' ? 1080 : 8080),
            'user' => $parsed['user'] ?? '',
            'pass' => $parsed['pass'] ?? '',
        ];
    }

    private function useProxy($custom_proxy = null) {
        if (!$this->use_proxy) {
            return null;
        }

        $proxy = $custom_proxy;
        
        if (!$proxy && Request::has("proxy")) {
            $encrypted_proxy = Request::param("proxy");
            $proxy = $this->secure_decrypt($encrypted_proxy, "MicxzyOnTop");
        }
        
        if (!$proxy) {
            $proxy = $this->getRandomProxy();
        }

        if ($proxy) {
            $parsed = $this->parseProxy($proxy);
            
            // Determine proxy type
            $proxy_type = CURLPROXY_HTTP;
            if ($parsed['scheme'] === 'socks5') {
                $proxy_type = CURLPROXY_SOCKS5;
            }
            
            UnirestRequest::proxy($parsed['host'], $parsed['port'], $proxy_type);
            
            if (!empty($parsed['user']) && !empty($parsed['pass'])) {
                UnirestRequest::proxyAuth($parsed['user'], $parsed['pass']);
            }
            
            return $proxy;
        }
        
        return null;
    }

    private function checkRateLimit($identifier) {
        if (!$this->rate_limit_config['enabled']) {
            return true;
        }

        $cache_key = $this->rate_limit_config['cache_key_prefix'] . md5($identifier);
        $cache_file = $this->cache_dir . '/' . $cache_key . '.txt';
        
        $now = time();
        $window = 60; // 1 minute window
        
        if (file_exists($cache_file)) {
            $data = unserialize(file_get_contents($cache_file));
            
            // Clean old requests outside the window
            $data = array_filter($data, function($time) use ($now, $window) {
                return ($now - $time) < $window;
            });
            
            if (count($data) >= $this->rate_limit_config['requests_per_minute']) {
                return false;
            }
            
            $data[] = $now;
            file_put_contents($cache_file, serialize($data));
        } else {
            file_put_contents($cache_file, serialize([$now]));
        }
        
        return true;
    }

    public function fcca() {
        // Check rate limit
        $client_ip = Request::ip();
        if (!$this->checkRateLimit('fcca_' . $client_ip)) {
            return json(['error' => 'Rate limit exceeded'])
                ->code(429);
        }

        $this->useProxy();

        $session_token = Request::param("session_token") ?? "";
        $game_token = Request::param("game_token") ?? "";
        $sid = Request::param("sid") ?? "";
        $guess = Request::param("guess") ?? "";
        $render_type = Request::param("render_type") ?? "";
        $analytics_tier = Request::param("analytics_tier") ?? "";
        $bio = Request::param("bio") ?? "";
        $is_compatibility_mode = Request::param("is_compatibility_mode") ?? "";

        $headers = [
            "Referer" => "https://arkoselabs.roblox.com/fc/assets/ec-game-core/game-core/1.27.4/standard/index.html?session=".$session_token."&r=ap-southeast-1&meta=3&metabgclr=transparent&metaiconclr=%23757575&maintxtclr=%23b8b8b8&guitextcolor=%23474747&pk=476068BF-9607-4799-B53D-966BE98E2B81&at=40&rid=97&ag=101&cdn_url=https%3A%2F%2Farkoselabs.roblox.com%2Fcdn%2Ffc&surl=https%3A%2F%2Farkoselabs.roblox.com&smurl=https%3A%2F%2Farkoselabs.roblox.com%2Fcdn%2Ffc%2Fassets%2Fstyle-manager&theme=default",
            "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
            "Accept" => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
            "Cache-Control" => "no-cache",
            "X-Requested-With" => "XMLHttpRequest",
            "X-NewRelic-Timestamp" => "170750100767813",
            "X-Requested-ID" => '{"ct":"3N7zfKMn+sD4f7skMOIdWnWvP2XwERuE+ZuB6Fkuy7Y=","iv":"507dfaaf7577b1b015722d76054c9ce4","s":"3622e1184adf85b3"}',
            "Sec-GPC" => "1",
            "Sec-Fetch-Dest" => "empty",
            "Sec-Fetch-Mode" => "cors",
            "Sec-Fetch-Site" => "same-origin",
        ];
        
        $formdata = "session_token=" . $session_token . 
                "&game_token=" . $game_token .
                "&sid=" . $sid .
                "&guess=" . urlencode($guess) . 
                "&render_type=" . $render_type .
                "&analytics_tier=" . $analytics_tier .
                "&bio=" . urlencode($bio) .
                "&is_compatibility_mode=" . $is_compatibility_mode;

        try {
            $response = UnirestRequest::post("https://arkoselabs.roblox.com/fc/ca/", $headers, $formdata);
            return json($response->body)
                ->code($response->code);
        } catch (\Exception $e) {
            return json(['error' => $e->getMessage()])
                ->code(500);
        }
    }

    public function gfct() {
        $client_ip = Request::ip();
        if (!$this->checkRateLimit('gfct_' . $client_ip)) {
            return json(['error' => 'Rate limit exceeded'])
                ->code(429);
        }

        $this->useProxy();

        $token = Request::param("token") ?? "None";

        $headers = [
            "Referer" => "https://arkoselabs.roblox.com/fc/assets/ec-game-core/game-core/1.27.4/standard/index.html?session=".$token."&r=ap-southeast-1&meta=3&metabgclr=transparent&metaiconclr=%23757575&maintxtclr=%23b8b8b8&guitextcolor=%23474747&pk=476068BF-9607-4799-B53D-966BE98E2B81&at=40&rid=97&ag=101&cdn_url=https%3A%2F%2Farkoselabs.roblox.com%2Fcdn%2Ffc&surl=https%3A%2F%2Farkoselabs.roblox.com&smurl=https%3A%2F%2Farkoselabs.roblox.com%2Fcdn%2Ffc%2Fassets%2Fstyle-manager&theme=default",
            "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
            "Accept" => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
            "Cache-Control" => "no-cache",
            "X-Requested-With" => "XMLHttpRequest",
            "X-NewRelic-Timestamp" => "170750100767813",
            "Sec-GPC" => "1",
            "Sec-Fetch-Dest" => "empty",
            "Sec-Fetch-Mode" => "cors",
            "Sec-Fetch-Site" => "same-origin",
        ];
        
        $data = "token=". $token.
                "&sid=sid=us-east-1" .
                "&render_type=canvas" .
                "&isAudioGame=" . false . 
                "&analytics_tier=40" .
                "&is_compatibility_mode=" . false .
                "&apiBreakerVersion=green";

        try {
            $response = UnirestRequest::post("https://arkoselabs.roblox.com/fc/gfct/", $headers, $data);
            $http_host = $_SERVER['HTTP_HOST'];
            $modified_response = json_encode($response->body);
            $modified_response = str_replace('arkoselabs.roblox.com', $http_host, $modified_response);

            return json(json_decode($modified_response))
                ->code($response->code);
        } catch (\Exception $e) {
            return json(['error' => $e->getMessage()])
                ->code(500);
        }
    }

    public function init_load() {
        $client_ip = Request::ip();
        if (!$this->checkRateLimit('init_load_' . $client_ip)) {
            return json(['error' => 'Rate limit exceeded'])
                ->code(429);
        }

        $this->useProxy();

        $session_token = Request::param("session_token") ?? "None";

        $headers = [
            "Referer" => "https://arkoselabs.roblox.com/v2/2.11.4/enforcement.9eab88fb89440e9080505ec7f1f1b658.html",
            "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
            "Accept-Language" => "en-US,en;q=0.5",
            "Cache-Control" => "no-cache",
            "Sec-Fetch-Dest" => "empty",
            "Sec-Fetch-Mode" => "cors",
            "Sec-Fetch-Site" => "same-origin",
        ];

        try {
            $response = UnirestRequest::get("https://arkoselabs.roblox.com/fc/init-load/?session_token=".urlencode($session_token)."", $headers);
            return response($response->body)
                ->code($response->code);
        } catch (\Exception $e) {
            return json(['error' => $e->getMessage()])
                ->code(500);
        }
    }

    public function rtigimage() {
        $client_ip = Request::ip();
        if (!$this->checkRateLimit('rtigimage_' . $client_ip)) {
            return json(['error' => 'Rate limit exceeded'])
                ->code(429);
        }

        $this->useProxy();

        $challenge = Request::param("challenge") ?? '';
        $expires = Request::param("expires") ?? '';
        $sessionToken = Request::param("sessionToken") ?? '';
        $gameToken = Request::param("gameToken") ?? '';
        $signature = Request::param("signature") ?? '';

        $url = sprintf(
            "https://arkoselabs.roblox.com/rtig/image?challenge=%s&expires=%s&sessionToken=%s&gameToken=%s&signature=%s",
            urlencode($challenge),
            urlencode($expires),
            urlencode($sessionToken),
            urlencode($gameToken),
            urlencode($signature)
        );

        $headers = [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:122.0) Gecko/20100101 Firefox/122.0",
            "Accept: */*",
            "Accept-Encoding: gzip, deflate, br, zstd",
            "Accept-Language: en-US,en;q=0.9",
            "Priority: u=1, i",
            "Referer: https://arkoselabs.roblox.com/fc/assets/ec-game-core/game-core/1.27.4/standard/index.html?session=" . urlencode($sessionToken) . "&r=ap-southeast-1&meta=3&metabgclr=transparent&metaiconclr=%23757575&maintxtclr=%23b8b8b8&guitextcolor=%23474747&pk=476068BF-9607-4799-B53D-966BE98E2B81&at=40&rid=95&ag=101&cdn_url=https%3A%2F%2Farkoselabs.roblox.com%2Fcdn%2Ffc&surl=https%3A%2F%2Farkoselabs.roblox.com&smurl=https%3A%2F%2Farkoselabs.roblox.com%2Fcdn%2Ffc%2Fassets%2Fstyle-manager&theme=default",
            "Sec-CH-UA: \"Chromium\";v=\"128\", \"Not;A=Brand\";v=\"24\", \"Opera GX\";v=\"114\"",
            "Sec-CH-UA-Mobile: ?0",
            "Sec-CH-UA-Platform: \"Windows\"",
            "Sec-Fetch-Dest: empty",
            "Sec-Fetch-Mode: cors",
            "Sec-Fetch-Site: same-origin",
        ];

        try {
            $response = UnirestRequest::get($url, $headers);
            return response($response->body)
                ->code($response->code);
        } catch (\Exception $e) {
            return json(['error' => $e->getMessage()])
                ->code(500);
        }
    }

    public function pkeytoken() {
        $client_ip = Request::ip();
        if (!$this->checkRateLimit('pkeytoken_' . $client_ip)) {
            return json(['error' => 'Rate limit exceeded'])
                ->code(429);
        }

        $this->useProxy();

        $bda = Request::param('bda') ?? '';
        $rnd = Request::param('rnd') ?? '';
        $capi_version = Request::param('capi_version') ?? '';
        $userbrowser = Request::param('userbrowser') ?? '';
        $blob = Request::param('data')['blob'] ?? '';

        $headers = array(
            "User-Agent" => $userbrowser,
            "Referer" => "https://arkoselabs.roblox.com/v2/2.11.4/enforcement.9eab88fb89440e9080505ec7f1f1b658.html",
            "Origin" => "https://arkoselabs.roblox.com",
            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
            "DNT" => "1",
            "TE" => "trailers",
            "x-ark-esync-value" => time()
        );
        
        $formdata = "bda=" . urlencode($bda) . 
                "&public_key=476068BF-9607-4799-B53D-966BE98E2B81" .
                "&site=https://www.roblox.com" .
                "&userbrowser=" . urlencode($userbrowser) . 
                "&capi_version=" . urlencode($capi_version) .
                "&capi_mode=inline" .
                "&style_theme=default" .
                "&rnd=" . urlencode($rnd) .
                "&data[blob]=" . urlencode($blob);

        try {
            $response = UnirestRequest::post(
                "https://arkoselabs.roblox.com/fc/gt2/public_key/476068BF-9607-4799-B53D-966BE98E2B81",
                $headers,
                $formdata
            );

            $responseBody = $response->body;
            return json($responseBody)->code($response->code);
        } catch (\Exception $e) {
            return json(['error' => $e->getMessage()])
                ->code(500);
        }
    }

    public function captcha() {
        return View::fetch('captcha');
    }

    public function health() {
        return json(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')])
            ->code(200);
    }
}
