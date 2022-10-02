FROM ubuntu:18.04

# Ubuntu 18.04:
RUN apt-get update -y && apt-get install -y python3-pip python3-dev build-essential curl libssl1.0.0 libssl-dev libpq-dev software-properties-common
# Ubuntu 20.04:
# RUN apt-get update -y && apt-get install -y python3-pip python3-dev build-essential curl libssl-dev libpq-dev
# See about installing ODBC drivers here: https://docs.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server?view=sql-server-2017
# Note that the driver version installed needs to match the version used in the code

# Python 3.8
# RUN add-apt-repository -y ppa:deadsnakes/ppa
# RUN apt-get update -y && apt-get install -y python3.8
# RUN curl -s https://bootstrap.pypa.io/get-pip.py -o get-pip.py
# RUN python3.8 get-pip.py

# Ubuntu 18.04 (ODBC SQL driver 17.0)
RUN curl -s https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
RUN curl -s https://packages.microsoft.com/config/ubuntu/18.04/prod.list > /etc/apt/sources.list.d/mssql-release.list
# Ubuntu 20.04 (ODBC SQL driver 18.0)
# RUN curl -s https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
# RUN curl -s https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/prod.list > /etc/apt/sources.list.d/mssql-release.list

ENV PYTHONIOENCODING=utf-8

RUN apt-get update -y --fix-missing
RUN ACCEPT_EULA=Y apt-get install -y msodbcsql17 unixodbc-dev mssql-tools

COPY . /app
WORKDIR /app
RUN pip3 install --upgrade pip
RUN pip3 install -r requirements.txt
CMD ["sh", "-c", "python3 sql_api.py"]

EXPOSE 8080
