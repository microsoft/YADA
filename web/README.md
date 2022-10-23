# Web frontend for YADA

## Usage

Simple PHP web page that can access the [YADA API](../api/README.md). It will show something like this:

![web](./homepage_screenshot.png)

The container requires these environment variables:

* `API_URL`: URL where the SQL API can be found, for example `http://1.2.3.4:8080` or `http://api:8080`
* `BACKGROUND` (optional): HTML color for the background, this is useful when deploying different versions of the app behind a LB. Some color examples:
  * `#aaf1f2`: cyan
  * `#92cb96`: light green
  * `#fcba87`: light orange
  * `#fdfbc0`: yellow
* `BRANDING` (optional): you can optionally modify the branding of YADA to match events, such as [What The Hack](https://aka.ms/wth) or [Openhack](https://openhack.microsoft.com).
* `HEADER_NAME` (optional): if used, it checks that a HTTP header exists and matches the value of `HEADER_VALUE`
* `HEADER_VALUE` (optional): if used, it is the value that needs to contain the header `HEADER_NAME` for the request to be accepted

The container offers as well the page `/healthcheck.html` to monitor the availability of the web server, and `/healthcheck.php` to verify that PHP is working fine.

## Build

 You can build it locally with:

```bash
docker build -t your_dockerhub_user/yadaweb:1.0 .
```

or in a registry such as Azure Container Registry with:

```bash
az acr build -r <your_acr_registry> -g <your_azure_resource_group> -t yadaweb:1.0 .
```
