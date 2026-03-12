# Deploying to Railway.app (Professional & Always-On)

Railway is an excellent platform for this project because it can host your **PHP App**, **MySQL**, **MongoDB**, and **Redis** all in one place.

## Step 1: Push Your Latest Code
Ensure your code is pushed to your GitHub repository.
`https://github.com/lohithashwas/ITEL_INTERNSHIP_TASK`

## Step 2: Create a New Project on Railway
1. Go to [Railway.app](https://railway.app/) and login with GitHub.
2. Click **+ New Project**.
3. Select **Deploy from GitHub repo** and choose `ITEL_INTERNSHIP_TASK`.
4. Click **Deploy Now**. (It will fail initially because the databases aren't set up yet—don't worry!)

## Step 3: Add the Databases
In your Railway project dashboard, click **+ New** and add:
1. **Database** -> **Add MySQL**
2. **Database** -> **Add MongoDB**
3. **Database** -> **Add Redis**

## Step 4: Configure Environment Variables
Go to your **Web Service** (the logic part) in Railway, click the **Variables** tab, and add the following:

| Variable Name | How to find it in Railway |
| :--- | :--- |
| `MYSQL_HOST` | Copy from MySQL service -> Variables -> `MYSQLHOST` |
| `MYSQL_USER` | Copy from MySQL service -> Variables -> `MYSQLUSER` |
| `MYSQL_PASSWORD` | Copy from MySQL service -> Variables -> `MYSQLPASSWORD` |
| `MYSQL_DATABASE` | Copy from MySQL service -> Variables -> `MYSQLDATABASE` |
| `MYSQL_PORT` | Copy from MySQL service -> Variables -> `MYSQLPORT` |
| `MONGODB_URI`| Copy from MongoDB service -> Variables -> `MONGODB_URL` |
| `REDIS_HOST` | Copy from Redis service -> Variables -> `REDISHOST` |
| `REDIS_PORT` | Copy from Redis service -> Variables -> `REDISPORT` |
| `REDIS_PASSWORD`| Copy from Redis service -> Variables -> `REDISPASSWORD` |

## Step 5: Final Deployment
1. Go back to your Web Service settings.
2. Under **Networking**, click **Generate Domain**. You will get a link like `your-app.up.railway.app`.
3. Go to the **Deployments** tab and click **Redeploy**.

Railway will use your `Dockerfile` to build the app. Once finished, your site will be **LIVE 24/7**, even if your laptop is closed!
