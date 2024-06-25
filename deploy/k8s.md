# YADA - Deployment on Kubernetes

In the README files for each tier ([web/README.md](../web/README.md) and [../api/README.md](api/README.md)) you can find additional information about YADA web and API components. In the following example you can find the simplest deployment of the YADA app using Kubernetes pods for the web and API tiers, and Azure SQL Database for the data tier.

If you don't have a database, you can deploy one using SQL Server:

```bash
# Variables
rg=rg$RANDOM
location=eastus
sql_server_name=sqlserver$RANDOM
sql_db_name=mydb
sql_username=azure
sql_password=$(openssl rand -base64 10)  # 10-character random password

# Create Resource Group
az group create -n $rg -l $location

# Create Azure SQL Server and database
az sql server create -n $sql_server_name -g $rg -l $location --admin-user "$sql_username" --admin-password "$sql_password"
az sql db create -n $sql_db_name -s $sql_server_name -g $rg -e Basic -c 5 --no-wait
sql_server_fqdn=$(az sql server show -n $sql_server_name -g $rg -o tsv --query fullyQualifiedDomainName) && echo $sql_server_fqdn
```

## API

You can use the sample manifest to deploy the API component, modifying the relevant environment variables. Note that this basic deployment doesn't use secrets or integration with Azure Key Vault:

```yml
kubectl apply -f - <<EOF
apiVersion: v1
kind: Secret
metadata:
  name: sqlpassword
type: Opaque
stringData:
  password: $sql_password
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
          value: "$sql_username"
        - name: SQL_SERVER_FQDN
          value: "$sql_server_fqdn"
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
  annotations:
    service.beta.kubernetes.io/azure-load-balancer-internal: "true"
spec:
  type: LoadBalancer
  ports:
  - port: 8080
    targetPort: 8080
  selector:
    run: api
EOF
```

## Web

You can use the sample manifest to deploy the web component. It uses `api` as the IP address of the API tier, leveraging Kubernetes DNS-based resolution of service names (it assumes the API service is deployed in the same namespace):

```yml
kubectl apply -f - <<EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  labels:
    run: web
  name: web
spec:
  replicas: 1
  selector:
    matchLabels:
      run: web
  template:
    metadata:
      labels:
        run: web
    spec:
      containers:
      - image: erjosito/yadaweb:1.0
        name: web
        ports:
        - containerPort: 80
          protocol: TCP
        env:
        - name: API_URL
          value: "http://api:8080"
      restartPolicy: Always
---
apiVersion: v1
kind: Service
metadata:
  name: web
  annotations:
    service.beta.kubernetes.io/azure-load-balancer-internal: "true"
spec:
  type: LoadBalancer
  ports:
  - port: 80
    targetPort: 80
  selector:
    run: web
EOF
```
