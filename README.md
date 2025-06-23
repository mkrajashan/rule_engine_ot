
# Simple Rule Engine


A **Dockerized Symfony REST API**

- **Authentication**: Get the access token
- **Upload**: 
```text
    Dependency File upload 
    Add Dependency File into Queue and send for scan
    Implemented Symfony Messenger allows to dispatch and consume messages asynchronously, enabling background processing of tasks. The process involves dispatching a message, which is then routed to a configured transport (like a queue), and finally consumed by a worker that executes the corresponding handler. 
    Once Scan has been complete trigger the Rules & Action Defined on the Message Bus
    Send Notification to the User ( Mail/ Slack ) -> Using Notifier service, any notification channel can be added
```
---

## 🚀 Quick Start

### 1. Clone & Setup

```bash
git clone https://github.com/mkrajashan/rule_engine_ot.git
cd rule_engine_ot
docker-compose up -d --build
./run-tests.sh
```

### 2. Clear & Migrate

```bash
docker exec -it rule_engine-php-1 bash
php bin/console doctrine:schema:drop --force
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console cache:clear --env=dev
```

---

## ✅ Testing

Run test cases (this will refresh the DB):

```bash
./run-tests.sh
```

---

## ✅ Code Analyzer

```bash
composer phpstan
```

---
---

## ✅ CI/CD Pipeline has been integrted on .Github/Workflows

```bash
.github/workflows/ci.yml
```

---
## 🧪 API Usage (Step-by-Step)

> **Note:** Use the Debricked useremail and password to get the token

### 1. 🔐 Authentication (Required)

- **Endpoint:** `POST http://localhost:8888/api/login-check`
- **Headers:** `Accept: application/json`
- **Form Data:**

```text
email: mukilmani7@gmail.com
password: mani@123
```

- **Response:**

```Sample json
{"token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzUxMiJ9.eyJpYXQiOjE3NTA2ODIyMDcsImV4cCI6MTc1MDY4NTgwNywicm9sZXMiOlsiUk9MRV9SRVBPU0lUT1JZX0FETUlOIiwiUk9MRV9DT01QQU5ZX0FETUlOIiwiUk9MRV9VU0VSIl0sImVtYWlsIjoibXVraWxtYW5pNzlAZ21haWwuY29tIn0.lHUGI_WexZZx65TDeHXq-J4RfbbVcM4pbKlr7Fhpzs7zvRmoy1RbbMpUnP3tcbc9ayqV3UlYs2xBW2OSk0K-bDmC_Nl63Gmh59iMshEWkAY5I0UNHFok8ixmpprPBZGzWmmIhDrcNfZGfoXbYcMpAqfa2JXs3Uq9-m2mNsLBVGefEl6dwbsQMHnKxPAsa2vrugn-P4TtjgvwpVM4bs9Ab9zgt8Mb6B_hJp5Zdcpn1ObPqYLyhQqrUjczZNQnhIBU54lhim93WckPOvI228k4N0jHrmJqdPGjhnOIZP5SqURDX0pVRMDiv1xlpnAMi_VcF3eham5SPkBUhuHf7cUFEOLtCDzuOC628daK1IsvB7cp7qPekM2DL7b1ReQvBlqXb5j-vU4i9lCPibIObxsxNsUsOPqc_-kWSW9_ByBnoncgAuuoDS66oAFhVCA4JPZPc7wDiXo2_Zv2snLnSBgKDz9ch3l6dYK007uyXcq5CVa86fLcYS5f6MOcUsqvqY4fnuZkJFkVjGmumukuLTFBH1VzjNQEOK4UG8Lle2R1KuJNkYgYw8IX3zYJFpPTSGpweeia9OI2-nVrfQwyqwa8BeoeOBMFWGUs9TSq3jtdMtAdqkrpMDGdeqNWkKqcAuQvElBUZPw6LZDX_JtZ8c_DUOOgw4r9ka87m22V-0_63Kw","status":200}
```

> **Note:** This token will be set default to the ENV['DEBRICKED_API_TOKEN'] file for the later use. No need to copy & send for the remaining API Access.

---

#### 3. File Upload API

- **POST http://localhost:8888/api/upload
```text
1.Set method to POST
2.Set URL to your upload endpoint (e.g. http://localhost:8000/api/upload)
3.In the Body, choose form-data
4.Use key as files[], type as File, and attach files.
5.You can add multiple entries with key files[] to upload multiple files.
```
- **Response:**
```json
{"status":"success","data":"completed"}
```
---
## 🛠 PHPMyAdmin

```bash
docker exec -it rule_engine-database-1 bash
mysql -u root -p
# password: docker
```
---

> **Note:**  
```text
update this value slack dsn with the real credentials
```

