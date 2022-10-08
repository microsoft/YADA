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

## Deploy

### Test this against an Azure SQL Database

You can deploy an Azure SQL Database to test this image with the Azure CLI:

```bash
# Create Database for testing
rg=rg$RANDOM
location=westeurope
sql_server_name=sqlserver$RANDOM
sql_db_name=mydb
sql_username=azure
sql_password=your_super_secret_password
az group create -n $rg -l $location
az sql server create -n $sql_server_name -g $rg -l $location --admin-user "$sql_username" --admin-password "$sql_password"
az sql db create -n $sql_db_name -s $sql_server_name -g $rg -e Basic -c 5 --no-wait
sql_server_fqdn=$(az sql server show -n $sql_server_name -g $rg -o tsv --query fullyQualifiedDomainName) && echo $sql_server_fqdn
```

Delete the RG when you are done

```bash
# Delete resource group
az group delete -n $rg -y --no-wait
```

### Run this image locally

Replace the image and the variables with the relevant values for your environment. If you are using a private registry, make sure to provide authentication parameters:

```bash
# Deploy API on Docker
docker run -d -p 8080:8080 -e "SQL_SERVER_FQDN=yourdatabase.com" -e "SQL_SERVER_USERNAME=your_db_admin_user" -e "SQL_SERVER_PASSWORD=your_db_admin_password" --name api erjosito/yadaapi:1.0
```

### Run this image in Azure Container Instances

Replace the image and the variables with the relevant values for your environment. If you are using a private registry, make sure to provide authentication parameters:

```bash
# Deploy API on ACI
rg=your_resource_group
sql_server_fqdn=your_db_fqdn
sql_username=your_db_admin_user
sql_password=your_db_admin_password
az container create -n api -g $rg \
    -e "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=$sql_server_fqdn" \
    --image erjosito/yadaapi:1.0 --ip-address public --ports 8080
```

If running the image from your own Azure Container Registry:

```bash
# Deploy API on ACI from ACR
acr_name=<your_ACR>
az acr update -n "$acr_name" --admin-enabled true
acr_usr=$(az acr credential show -n "$acr_name" -g "$rg" --query 'username' -o tsv)
acr_pwd=$(az acr credential show -n "$acr_name" -g "$rg" --query 'passwords[0].value' -o tsv)
az container create -n api -g $rg  \
    -e "SQL_SERVER_USERNAME=${sql_username}" "SQL_SERVER_PASSWORD=${sql_password}" "SQL_SERVER_FQDN=${sql_server_fqdn}" \
    --image "${acr_name}.azurecr.io/yadaapi:1.0" --ip-address public --ports 8080 \
    --registry-username "$acr_usr" --registry-password "$acr_pwd"
```

If testing access to Azure Key Vault, you might want to deploy the image in an ACI associated to a managed identity, and instead of supplying a database password you can provide a Key Vault and secret name:

```bash
# Deploy API on ACI with managed identity
rg=<your_resource_group>
akv_name=<your_azure_keyvault>
akv_secret_name=<your_db_password_akv_secret_name>
identity_name=myACIid
az identity create -n $identity_name -g $rg
identity_spid=$(az identity show -g $rg -n $identity_name --query principalId -o tsv)
identity_appid=$(az identity show -g $rg -n $identity_name --query clientId -o tsv)
identity_id=$(az identity show -g $rg -n $identity_name --query id -o tsv)
az keyvault set-policy -n $akv_name -g $rg --object-id $identity_spid --secret-permissions get list
az container create -n api -g $rg \
    -e "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_FQDN=$sql_server_fqdn" "AKV_NAME=$akv_name" "AKV_SECRET_NAME=$akv_secret_name" \
    --image erjosito/yadaapi:1.0 --ip-address public --ports 8080 --assign-identity $identity_id
```

In any case, you might need to open up the SQL Server firewall to the egress IP address of the Azure Container Instance. Note that the egress IP of an Azure Container Instance is not necessarily the same as the inbound public IP address associated to the container, so you can use the `api/ip` endpoint of the application to find it out:

```bash
# Update Azure SQL Server IP firewall with ACI container IP
api_ip=$(az container show -n api -g "$rg" --query ipAddress.ip -o tsv)
api_egress_ip=$(curl -s "http://${api_ip}:8080/api/ip" | jq -r .my_public_ip)
az sql server firewall-rule create -g "$rg" -s "$sql_server_name" -n public_api_aci-source --start-ip-address "$api_egress_ip" --end-ip-address "$api_egress_ip"
```

### Run this image in Azure Container Instances with an nginx sidecar container

You can use a YAML-based deployment to include two containers into the group: one with the API listening on port 8080, and another one with nginx providing TLS:

```bash
# Create cert
openssl req -new -newkey rsa:2048 -nodes -keyout /tmp/ssl.key -out /tmp/ssl.csr -subj "/C=US/ST=US/O=Self Signed/CN=Self Signed Cert"
openssl x509 -req -days 365 -in /tmp/ssl.csr -signkey /tmp/ssl.key -out /tmp/ssl.crt
ssl_crt=$(cat /tmp/ssl.crt | base64)
ssl_key=$(cat /tmp/ssl.key | base64)
# Create nginx conf
cat <<EOF > /tmp/nginx.conf
user nginx;
worker_processes auto;
events {
  worker_connections 1024;
}
pid        /var/run/nginx.pid;
http {
    server {
        listen 443 ssl;
        server_name localhost;
        ssl_protocols              TLSv1.2;
        ssl_ciphers                ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:ECDHE-RSA-RC4-SHA:ECDHE-ECDSA-RC4-SHA:AES128:AES256:RC4-SHA:HIGH:!aNULL:!eNULL:!EXPORT:!DES:!3DES:!MD5:!PSK;
        ssl_prefer_server_ciphers  on;
        ssl_session_cache    shared:SSL:10m; # a 1mb cache can hold about 4000 sessions, so we can hold 40000 sessions
        ssl_session_timeout  24h;
        keepalive_timeout 300; # up from 75 secs default
        add_header Strict-Transport-Security 'max-age=31536000; includeSubDomains';
        ssl_certificate      /etc/nginx/ssl.crt;
        ssl_certificate_key  /etc/nginx/ssl.key;
        location /api/ {
            proxy_pass http://127.0.0.1:8080 ;
            proxy_set_header Connection "";
            proxy_set_header Host \$host;
            proxy_set_header X-Real-IP \$remote_addr;
            proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
            # proxy_set_header X-Forwarded-For \$remote_addr;
        }
    }
}
EOF
nginx_conf=$(cat /tmp/nginx.conf | base64)
# Create ACI deployment file
cat <<EOF > /tmp/deploy-aci.yaml
api-version: 2019-12-01
location: $location
name: api
properties:
  containers:
  - name: nginx
    properties:
      image: mcr.microsoft.com/oss/nginx/nginx:1.15.5-alpine
      ports:
      - port: 443
        protocol: TCP
      resources:
        requests:
          cpu: 1.0
          memoryInGB: 1.5
      volumeMounts:
      - name: nginx-config
        mountPath: /etc/nginx
  - name: api
    properties:
      image: erjosito/yadaapi:1.0
      environmentVariables:
        - name: 'SQL_SERVER_FQDN'
          value: '$sql_server_fqdn'
        - name: 'SQL_SERVER_USER'
          value: '$sql_username'
        - name: 'SQL_SERVER_PASSWORD'
          secureValue: '$sql_password'
      ports:
      - port: 8080
        protocol: TCP
      resources:
        requests:
          cpu: 0.5
          memoryInGB: 0.5
  volumes:
  - secret:
      ssl.crt: "$ssl_crt"
      ssl.key: "$ssl_key"
      nginx.conf: "$nginx_conf"
    name: nginx-config
  ipAddress:
    ports:
    - port: 443
      protocol: TCP
    type: Public
  osType: Linux
tags: null
type: Microsoft.ContainerInstance/containerGroups
EOF
# Deploy container group
az container create -g $rg --file /tmp/deploy-aci.yaml -o none
```

### Run this image in Kubernetes

You can use the sample manifest to deploy this container, modifying the relevant environment variables and source image:

```yml
apiVersion: v1
kind: Secret
metadata:
  name: sqlpassword
type: Opaque
stringData:
  password: your_db_admin_password
---
apiVersion: apps/v1
kind: Deployment
metadata:
  labels:
    run: api
  name: api
spec:
  replicas: 1
  selector:
    matchLabels:
      run: api
  template:
    metadata:
      labels:
        run: api
    spec:
      containers:
      - image: erjosito/yadaapi:1.0
        name: api
        ports:
        - containerPort: 8080
          protocol: TCP
        env:
        - name: SQL_SERVER_USERNAME
          value: "your_db_admin_username"
        - name: SQL_SERVER_FQDN
          value: "your_db_server_fqdn"
        - name: SQL_SERVER_PASSWORD
          valueFrom:
            secretKeyRef:
              name: sqlpassword
              key: password
      restartPolicy: Always
---
apiVersion: v1
kind: Service
metadata:
  name: api
spec:
  type: LoadBalancer
  ports:
  - port: 8080
    targetPort: 8080
  selector:
    run: api
```

### Run this image on Azure App Services

This example Azure CLI code deploys the image on Azure Application Services (aka Web App):

```bash
# Run on Web App
rg=your_resource_group
sql_server_fqdn=your_db_fqdn
sql_username=your_db_admin_user
sql_password=your_db_admin_password
svcplan_name=webappplan
app_name_api=api-$RANDOM
az appservice plan create -n $svcplan_name -g $rg --sku B1 --is-linux
az webapp create -n $app_name_api -g $rg -p $svcplan_name --deployment-container-image-name erjosito/yadaapi:1.0
az webapp config appsettings set -n $app_name_api -g $rg --settings "WEBSITES_PORT=8080" "SQL_SERVER_USERNAME=$sql_username" "SQL_SERVER_PASSWORD=$sql_password" "SQL_SERVER_FQDN=${sql_server_fqdn}"
az webapp restart -n $app_name_api -g $rg
app_url_api=$(az webapp show -n $app_name_api -g $rg --query defaultHostName -o tsv) && echo $app_url_api
```
