# Deploy on Render (Free) + MongoDB Atlas (Free)

This project deploys on Render using Docker because it needs `ext-mongodb`.

## 1. Prepare MongoDB Atlas

1. Create an M0 free cluster in MongoDB Atlas.
2. Create a database user.
3. Allow network access from Render (for setup you can allow `0.0.0.0/0`, then tighten later).
4. Copy your connection string.

## 2. Push this repo to GitHub

Render deploys from GitHub/GitLab/Bitbucket repositories.

## 3. Deploy on Render

1. In Render, create a **New Web Service** from your repo.
2. Render will detect `render.yaml` automatically.
3. Set required secret environment variables in Render dashboard:
   - `MONGO_URI`
   - `DEFAULT_ADMIN_PASSWORD`
4. Click **Deploy**.

## 4. Verify deployment

After deploy, open:

- `/api/health`
- `/`

If `/api/health` works, your app and MongoDB connection are up.

## 5. Optional post-deploy hardening

1. Change default admin username/password.
2. Restrict Atlas network access to only required sources.
3. Keep `APP_DEBUG=false` in production.
