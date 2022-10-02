<!DOCTYPE html>
<?php
if (getenv('BRANDING') == 'openhack') {
    $logo_image = "OpenHackLogoTP.png"
} elseif (getenv('BRANDING') == 'whatthehack') {
    $logo_image = "wth-logo.png"
} else {
    $logo_image = "yada-logo.png"
}
?>
<html lang="en">
    <head>
        <title>Contoso Mortgage Company</title>
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
				<h1 class="header-heading">OpenHack Network Diagnostics</h1>
			</div>
		</div>
		<div class="nav-bar">
			<div class="container">
				<ul class="nav">
                <li><a href="index.php">Home</a></li>
                    <li><a href="info.php">Info</a></li>
                    <li><a hef="https://openhack.microsoft.com/">Microsoft OpenHacks</a></li>
					<li><a href="healthcheck.html">Healthcheck</a></li>
					<li><a href="healthcheck.php">PHPinfo</a></li>
                    <!--
					<li><a href="https://github.com/erjosito/whoami/blob/master/api/README.md">API docs</a></li>
					<li><a href="https://github.com/erjosito/whoami/blob/master/web/README.md">Web docs</a></li>
                    -->
                    <!--
                    <li style="color:LightGray;"><?php
                        $jwt = $_SERVER['HTTP_AUTHORIZATION'];
                        if (empty($jwt)) {
                            print ("Not authenticated");
                        } else {
                            list($header, $payload, $signature) = explode(".", $jwt);
                            $plainPayload = base64_decode($payload);
                            $jsonPayload = json_decode($plainPayload, true);
                            print("Hello, ".$jsonPayload["given_name"]); 
                        }
                    ?></li>
                    -->
				</ul>
			</div>
		</div>

        <div class="content" <?php print((empty(getenv("BACKGROUND"))) ? "" : "style=\"background-color:\"" . getenv("BACKGROUND") . ";\"");?> >
			<div class="container">
				<div class="main">
                    <h1><?php
                        $cmd = "curl -H Metadata:true http://169.254.169.254/metadata/instance?api-version=2017-08-01";
                        $metadataJson = shell_exec($cmd);
                        $result = json_decode($metadataJson, true);
                        print($result["compute"]["name"]);
                    ?></h1>
                    <p>This troubleshooting app is used as sample application in the Secure Networking <a href="https://openhack.microsoft.com/">OpenHack</a>. It is a 3-tier architecture with a web component (where this page is displayed), a REST API and a database. The rest API needs outbound Internet connectivity to a public API (https://jsonip.com):</p>
                    <p style="text-align:center;"><img src="app_arch.png" alt="Application Architecture" width="80%"></p>
                    <h3>HTTP headers</h3>
                    <p>Some of the HTTP headers in your request to this web server:</p>
                        <ul>
                            <li>HOST: <?php print($_SERVER['HTTP_HOST'])?></li>
                            <li>X-FORWARDED-FOR: <?php print($_SERVER['HTTP_X_FORWARDED_FOR'])?></li>
                            <li>REMOTE-ADDRESS: <?php print($_SERVER['REMOTE_ADDR'])?></li>
                            <li>USER-AGENT: <?php print($_SERVER['HTTP_USER_AGENT'])?></li>
                        </ul>
                    <h3>Information retrieved from API <?php print(getenv("API_URL"));?>:</h3>
                    <p>This section contains information resulting of calling some endpoints of the API. This will only work if the environment variable API_URL is set to the correct instance of an instance of the <a href="https://github.com/erjosito/whoami/blob/master/web/README.md">SQL API</a>:</p>
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
                        $cmd = "curl --connect-timeout 3 " . getenv("API_URL") . "/api/ip";
                        $result_json = shell_exec($cmd);
                        $result = json_decode($result_json, true);
                    ?>
                        <li>Connectivity info for application tier (API endpoint /api/ip):
                            <ul>
                            <li>Private IP address: <?php print($result["my_private_ip"]); ?></li>
                            <li>Public (egress) IP address: <?php print($result["my_public_ip"]); ?></li>
                            <li>Default gateway: <?php print($result["my_default_gateway"]); ?></li>
                            <li>HTTP request source IP address: <?php print($result["your_address"]); ?></li>
                            <li>HTTP request X-Forwarded-For header: <?php print($result["x-forwarded-for"]); ?></li>
                            <li>HTTP request Host header: <?php print($result["host"]); ?></li>
                            <li>HTTP requested path: <?php print($result["path_accessed"]); ?></li>
                            </ul>
                        </li>
                    </ul>
                    <br>
                    <h3>Other API calls</h3>
                    <p>The following sections show how to call other endpoints of the API:</p>

                    <hr>
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
                &copy; Copyleft 2020
            </div>
        </div>
    </body>
</html>
