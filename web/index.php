<!DOCTYPE html>
<?php
// If header verification is ON, this is hte first thing we do
$headers = getallheaders();
$key = ucwords(strtolower(getenv('HEADER_NAME')));
if (! (empty($key))) {
    if (array_key_exists($key, $headers)) {
        if ($headers[$key] != getenv('HEADER_VALUE')) {
            print("Mmmmh it looks like you are doing something that you really shouldn't: header " . $key . " is " . $headers[$key]);
            //var_dump($headers);
            http_response_code(403);
            return 0;
        }
    } else {
        print("Mmmmh it looks like you are doing something that you really shouldn't: I could not find the header " . $key);
        http_response_code(403);
        return 0;
    }
}
?>
<?php
// OpenHack branding
if (getenv('BRANDING') == 'openhack') {
    $logo_image = "OpenHackLogoTP.png";
    $platform = "vm";
    $page_title = "Contoso Mortgage Company";
    $h1_header = "OpenHack Network Diagnostics";
    $first_link_text = "OpenHacks";
    $first_link_url = "https://openhack.microsoft.com/";
    $first_line = "This diagnostics app is used as sample application in the Secure Networking <a href=\"https://openhack.microsoft.com/\">OpenHack</a>. ";
    $show_web_docs = "no";
    $show_api_docs = "no";
    $show_auth = "no";
    $show_ingress_section = "no";
    $show_api_ip = "no";
// WTH branding
} elseif (getenv('BRANDING') == 'whatthehack') {
    $logo_image = "wth-logo.png";
    $platform = "container";
    $page_title = "WTH Diagnostics App";
    $h1_header = "WTH Diagnostics App";
    $first_link_text = "WhatTheHack";
    $first_link_url = "https://aka.ms/wth";
    $first_line = "This diagnostics app is used as sample application in the <a href=\"https://microsoft.github.io/WhatTheHack/039-AKSEnterpriseGrade/\">Enterprise-grade AKS hack</a>. ";
    $show_web_docs = "yes";
    $show_api_docs = "yes";
    $show_auth = "no";
    $show_ingress_section = "yes";
    $show_api_ip = "yes";
// Default YADA branding
} else {
    $logo_image = "yada-logo.png";
    $platform = "container";
    $page_title = "Yet Another Demo App";
    $h1_header = "Diagnostics 3-Tier Application";
    $first_link_text = "Repo";
    $first_link_url = "https://github.com/microsoft/YADA";
    $first_line = "This is a sample diagnostics app that can be used to explore the infrastructure where it is deployed. ";
    $show_web_docs = "yes";
    $show_api_docs = "yes";
    $show_auth = "yes";
    $show_ingress_section = "yes";
    $show_api_ip = "yes";
}
// Depending on platform get hostname (either from OS or from IMDS)
if ($platform == "vm") {
    $cmd = "curl -s --connect-timeout 1 -H Metadata:true http://169.254.169.254/metadata/instance?api-version=2017-08-01";
    $metadataJson = shell_exec($cmd);
    # If no IMDS access fall back to the OS name
    if (!(empty($metadataJson))) {
        $metadata = json_decode($metadataJson, true);
        $hostname = $metadata['compute']['name'];
    } else {
        $hostname = shell_exec('hostname');
    }
} else {
    $hostname = shell_exec('hostname');
}
?>
<html lang="en">
    <head>
        <title><?php print($page_title); ?></title>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="Description" lang="en" content="Azure VM Info Page">
        <meta name="author" content="Jose Moreno">
        <meta name="robots" content="index, follow">
        <!-- icons -->
        <link rel="apple-touch-icon" href="apple-touch-icon.png">
        <link rel="shortcut icon" href="favicon.ico">
        <!-- CSS file -->
        <link rel="stylesheet" href="styles.css">
    </head>
    <body>
        <div class="header">
            <div class="container">
            <p style="text-align:center;"><img src="<?php print($logo_image); ?>" alt="App logo" width="80%"></p>
                <h1 class="header-heading"><?php print($h1_header); ?></h1>
            </div>
        </div>
        <div class="nav-bar">
            <div class="container">
                <ul class="nav">
                <li><a href="index.php">Home</a></li>
                    <li><a href="info.php">Info</a></li>
                    <li><a target="_blank" href="<?php print($first_link_url); ?>"><?php print($first_link_text); ?></a></li>
                    <li><a href="healthcheck.html">Healthcheck</a></li>
                    <li><a href="healthcheck.php">PHPinfo</a></li>
                    <?php
                    if ($show_web_docs == "yes") {
                        print('                    <li><a target="_blank" href="https://github.com/Microsoft/YADA/blob/master/web/README.md">Web docs</a></li>');
                    }
                    ?><?php
                    if ($show_api_docs == "yes") {
                        print('                    <li><a target="_blank" href="https://github.com/Microsoft/YADA/blob/master/api/README.md">API docs</a></li>');
                    }
                    ?>
                    <li style="color:LightGray;"><?php
                        if ($show_auth == "yes") {
                            $jwt = $_SERVER['HTTP_AUTHORIZATION'];
                            if (empty($jwt)) {
                                print ("Not authenticated");
                            } else {
                                list($header, $payload, $signature) = explode(".", $jwt);
                                $plainPayload = base64_decode($payload);
                                $jsonPayload = json_decode($plainPayload, true);
                                print("Hello, ".$jsonPayload["given_name"]); 
                            }
                        }
                    ?></li>
                </ul>
            </div>
        </div>

        <div class="content" <?php print((empty(getenv("BACKGROUND"))) ? "" : "style=\"background-color:" . getenv("BACKGROUND") . ";\"");?> >
            <div class="container">
                <div class="main">
                    <h1><?php print($hostname); ?></h1>
                    <p><?php print ($first_line); ?>It is a 3-tier architecture with a web component (where this page is displayed), a REST API and a database. The rest API needs outbound Internet connectivity to a public API (https://jsonip.com):</p>
                    <p style="text-align:center;"><img src="app_arch.png" alt="Application Architecture" width="80%"></p>
                    <h3>Information retrieved from API <?php print(getenv("API_URL"));?>:</h3>
                    <p>This section contains information resulting of calling some endpoints of the API. This will only work if the environment variable API_URL is set to the correct instance of an instance of the API:</p>
                    <ul>
                    <?php
                        $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/healthcheck";
                        $result_json = shell_exec($cmd);
                        $result = json_decode($result_json, true);
                        $healthcheck = $result["health"];
                    ?>
                        <li>Healthcheck: <?php print ($healthcheck); ?></li>
                    <?php
                        $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/sqlversion";
                        $result_json = shell_exec($cmd);
                        $result = json_decode($result_json, true);
                        $sql_output = $result["sql_output"];
                    ?>
                        <li>SQL Server version: <?php print($sql_output); ?></li>
                    <?php
                        if ($show_api_ip == "yes") {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/ip";
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("                        <li>Connectivity info for application tier (API endpoint /api/ip):");
                            print("                            <ul>");
                            print("                            <li>Private IP address: " . $result["my_private_ip"] . "</li>");
                            print("                            <li>Public (egress) IP address: " . $result["my_public_ip"] . "</li>");
                            print("                            <li>Default gateway: " . $result["my_default_gateway"] . "</li>");
                            print("                            <li>HTTP request source IP address: " . $result["your_address"] . "</li>");
                            print("                            <li>HTTP request X-Forwarded-For header: " . $result["x-forwarded-for"] . "</li>");
                            print("                            <li>HTTP request Host header: " . $result["host"] . "</li>");
                            print("                            <li>HTTP requested path: " . $result["path_accessed"] . "</li>");
                            print("                            </ul>");
                            print("                        </li>");
                        }
                    ?>
                    </ul>
                    <?php
                    if ($show_ingress_section == "yes") {
                        print("                    <br>");
                        print("                    <h3>Direct access to API</h3>");
                        print("                    <p>Note that these links will only work if used with a reverse-proxy, such an ingress controller in Kubernetes or an Azure Application Gateway</p>");
                        print("                    <ul>");
                        print("                        <li><a href='/api/healthcheck'>API health status</a></li>");
                        print("                        <li><a href='/api/sqlversion'>SQL Server version</a></li>");
                        print("                        <li><a href='/api/ip'>API connectivity information</a></li>");
                        print("                    </ul>");
                    }
                    ?>
                    <br>
                    <h3>HTTP headers</h3>
                    <p>HTTP headers in your request to this web server:</p>
                    <ul>
                    <?php
                        foreach($_SERVER as $h=>$v) {
                            $retval = preg_match('/HTTP_(.+)/i', $h);
                            if ($retval == 1) {
                                // Change underscores per hyphens, change capitalization
                                $h_name = ucwords(strtolower(str_replace('_', '-', substr($h, 5))), '-');
                                print ("<li>" . $h_name . " = " . $v . "</li>\n");
                            }
                        }
                    ?>
                    </ul>
                    <br>
                    <h3>Other API calls</h3>
                    <p>The following sections show how to call other endpoints of the API:</p>

                    <h4 id="api-sqlsrcip">SQL source IP</h4>
                    <p>Source IP from which the database sees the API:</p>
                    <form action="index.php#api-sqlsrcip" method="get">
                        <input type="hidden" id="command" name="command" value="sqlsrcip">
                        <input type="submit">
                    </form>
                    <?php 
                        if (strcmp($_GET["command"], 'sqlsrcip') == 0) {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/sqlsrcip";
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("<p>Result: <b>" . $result["sql_output"] . "</b></p>");
                        }
                    ?>

                    <hr>
                    <h4 id="api-dns">Resolve DNS name</h4>
                    <form action="index.php#api-dns" method="get">
                        Fully qualified domain name to resolve: <input type="text" name="fqdn" value="<?php print($_GET["fqdn"]); ?>"><br>
                        <input type="hidden" id="command" name="command" value="dns">
                        <input type="submit">
                    </form>
                    <?php 
                        if (strcmp($_GET["command"], 'dns') == 0) {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/dns?fqdn=" . $_GET["fqdn"];
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("<p>Result: <b>" . $result["ip"] . "</b></p>");
                        }
                    ?>

                    <hr>
                    <h4 id="api-reversedns">Reverse DNS lookup for IP address</h4>
                    <form action="index.php#reversedns" method="get">
                        IP address: <input type="text" name="ip" value="<?php print($_GET["ip"]); ?>"><br>
                        <input type="hidden" id="command" name="command" value="reversedns">
                        <input type="submit">
                    </form>
                    <?php 
                        if (strcmp($_GET["command"], 'reversedns') == 0) {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/reversedns?ip=" . $_GET["ip"];
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("<p>Result: <b>" . $result["fqdn"][0] . "</b></p>");
                        }
                    ?>

                    <hr>
                    <h4 id="api-curl">Send HTTP request</h4>
                    <p>This API endpoint will trigger an HTTP GET request from the application component to the URL you specify:</p>
                    <form action="index.php#api-curl" method="get">
                        URL to call (including http://): <input type="text" name="url" value="<?php print($_GET["url"]); ?>"><br>
                        <input type="hidden" id="command" name="command" value="curl">
                        <input type="submit">
                    </form>
                    <?php 
                        if (strcmp($_GET["command"], 'curl') == 0) {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/curl?url=" . $_GET["url"];
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("<p>Result: <b>" . $result["answer"] . "</b></p>");
                        }
                    ?>

                    <hr>
                    <h4 id="api-printenv">Retrieve environment variables</h4>
                    <p>This API endpoint will print all environment variables in the API component:</p>
                    <form action="index.php#api-printenv" method="get">
                        <input type="hidden" id="command" name="command" value="printenv">
                        <input type="submit">
                    </form>
                    <?php 
                        if (strcmp($_GET["command"], 'printenv') == 0) {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/printenv";
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("<ul>");
                            foreach($result as $key => $value) {
                                print("<li>" . $key . ": " . $result[$key] . "</li>");
                            }
                            print("</ul>");
                        }
                    ?>

                    <hr>
                    <h4 id="api-pi">Calculate number Pi</h4>
                    <p>This API call can be used to increase the CPU utilization of the application app component to test autoscaling technologies</p>
                    <form action="index.php#api-pi" method="get">
                        Number of digits to calculate: <input type="text" name="digits" value="<?php print($_GET["digits"]); ?>"><br>
                        <input type="hidden" id="command" name="command" value="pi">
                        <input type="submit">
                    </form>
                    <style>
                        piarea {
                            width: 100%;
                            height: 100px;
                            word-wrap: break-word;
                        }
                    </style>
                    <?php 
                        if (strcmp($_GET["command"], 'pi') == 0) {
                            $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/pi?digits=" . $_GET["digits"];
                            $result_json = shell_exec($cmd);
                            $result = json_decode($result_json, true);
                            print("<piarea>Result: <b>" . $result["pi"] . "</b></piarea>");
                        }
                    ?>
                </div>
            </div>
        </div>
        <div class="footer">
            <div class="container">
                MIT License
            </div>
        </div>
    </body>
</html>
