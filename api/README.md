# YADA API Container

## Usage

Here you can find the source files to build this container. The container is a web API that returns JSON payload. It offers the following endpoints:

* `/api/healthcheck`: returns a basic JSON code to verify if the application is running, it can be used for liveness probes
* `/api/sqlversion`: returns the results of a SQL query (`SELECT @@VERSION` for SQL Server or `SELECT VERSION();` for MySQL/Postgres) against a SQL database. You can override the value of the `SQL_SERVER_FQDN` via a query parameter 
* `/api/sqlsrcip`: returns the results of a SQL query (`SELECT CONNECTIONPROPERTY("client_net_address")` for SQL Server, `SELECT host FROM information_schema.processlist WHERE ID=connection_id();` for MySQL or `SELECT inet_client_addr ();` for Postgres) against a SQL database. You can override the value of the `SQL_SERVER_FQDN`, `SQL_SERVER_USERNAME`, `SQL_SERVER_PASSWORD` and `SQL_SERVER_ENGINE` via a query parameter
* `/api/ip`: returns information about the IP configuration of the container, such as private IP address, egress public IP address, default gateway, DNS servers, etc
* `/api/dns`: returns the IP address resolved from the FQDN supplied in the parameter `fqdn`
* `/api/reversedns`: returns the FQDN resolved with reverse DNS for the IP specified in the parameter `ip`
* `/api/printenv`: returns the environment variables for the container
* `/api/imds`: gets the instance metadata service. Note that the IMDS endpoint is blocked if running on ACI
* `/api/msitoken`: gets a token from the auth endpoint, if a system or user managed identity has been assigned to the VM or the container
* `/api/headers`: returns the HTTP headers of the request
* `/api/cookies`: returns the HTTP cookies of the request
* `/api/curl`: returns the output of a curl request, you can specify the argument with the parameter `url`
* `/api/pi`: calculates the decimals of the number pi, you can specify how many decimals with the parameter `digits`. 1,000 digits should be quick, but as you keep increasing the number of digits, more CPU will be required. You can use this endpoint to force the container to consume more CPU
* `/api/ioperf`: runs a quick performance check on the file system. It takes these parameters: `file` (default "/tmp/iotest"), `size` (default 128, in MB), `writeblocksize` (default 128, in KB) and `readblocksize` (default 8, in KB)
* `/api/sqlsrcipinit`: the previous endpoints do not modify the database. If you want to modify the database, you need first to create a table with this endpoint
* `/api/sqlsrciplog`: this endpoint will create a new record in the table created with the previous endpoint (`sqlsrcipinit`) with a timestamp and the source IP address as seen by the database.
* `/api/akvsecret`: this endpoint will try to retrieve a secret from an Azure Key Vault. It requires the parameters `akvname` and `akvsecret`. This can be used to test Azure authentication using concepts like pod/workload identity.

The container supports these environment variables:

* `SQL_SERVER_FQDN`: FQDN of the SQL server
* `SQL_SERVER_DB` (optional): FQDN of the SQL server
* `SQL_SERVER_USERNAME`: username for the SQL server
* `SQL_SERVER_PASSWORD`: password for the SQL server
* `SQL_ENGINE`: can be either `sqlserver`, `mysql` or `postgres`
* `AKV_NAME` (optional): if not specifying a password to access the database, you can supply the name of an Azure Key Vault to retrieve it from
* `AKV_SECRET_NAME` (optional): if not specifying a password to access the database, you can supply the name of a secret in an Azure Key Vault to retrieve it from
* `PORT` (optional): TCP port where the web server will be listening (8080 per default)

Note that environment variables can also be injected as files in the `/secrets` directory.

## Build

You can build the image locally with:

```bash
docker build -t your_dockerhub_user/yadaapi:1.0 .
```

or in a registry such as Azure Container Registry with:

```bash
az acr build -r <your_acr_registry> -g <your_azure_resource_group> -t yadaapi:1.0 .
```
