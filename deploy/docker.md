# YADA - Deployment on local Docker containers

In the README files for each tier ([web/README.md](../web/README.md) and [../api/README.md](api/README.md)) you can find additional information about YADA web and API components. In the following example you can find the simplest deployment of the YADA app using local Docker containers for each application tier.

If you don't have a database, you can deploy one using SQL Server:

```bash
# Deploy SQL Server as Docker image
sql_password='Microsoft123!Microsoft123!'
docker run -e "ACCEPT_EULA=Y" -e "SA_PASSWORD=${sql_password}" -p 1433:1433 --name sql -d mcr.microsoft.com/mssql/server:latest
```

Now you can deploy the API tier. You can find out the IP address of the database container with `docker inspect`:

```bash
# Find out IP address of database
sql_ip=$(docker inspect sql | jq -r '.[0].NetworkSettings.Networks.bridge.IPAddress')
# Deploy API on Docker
docker run -d -p 8080:8080 -e "SQL_SERVER_FQDN=${sql_ip}" -e "SQL_SERVER_USERNAME=sa" -e "SQL_SERVER_PASSWORD=your_db_admin_password" --name api erjosito/yadaapi:1.0
```

You can use `docker inspect` to find out the IP address of the API container

```bash
# Find out IP address of API
api_ip=$(docker inspect api | jq -r '.[0].NetworkSettings.Networks.bridge.IPAddress')
# Deploy web on Docker
docker run -d -p 8081:80 -e "API_URL=http://${api_ip}:8080" --name web erjosito/yadaweb:1.0
```
