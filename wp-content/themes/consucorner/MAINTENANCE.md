# ConsuCorner Theme — Maintenance Guide

A friendly, step-by-step handbook for the **ConsuCorner WordPress theme**.

Written for a junior developer (and explained simply enough for a 7‑year‑old to follow along). If you can copy, paste, click, and ask for help — you can maintain this theme.

> If anything in this guide ever feels confusing or risky, **stop** and call a senior developer before you continue. Live sites are easy to break and slow to fix.

---

## Table of Contents

1. [The Picture (How Everything Talks to Each Other)](#1-the-picture-how-everything-talks-to-each-other)
2. [Tools You Need (One Time Only)](#2-tools-you-need-one-time-only)
3. [Get the Project on Your Computer](#3-get-the-project-on-your-computer)
4. [The Daily Workflow (Edit → Save → Share)](#4-the-daily-workflow-edit--save--share)
5. [Branches: Working Without Breaking Things](#5-branches-working-without-breaking-things)
6. [Pull Requests: Showing Your Work](#6-pull-requests-showing-your-work)
7. [Deploying to the Live Server (Cloudways + Git)](#7-deploying-to-the-live-server-cloudways--git)
8. [Working with Other Developers](#8-working-with-other-developers)
9. [Roles: Who Does What](#9-roles-who-does-what)
10. [Safety Rules (Read This Twice)](#10-safety-rules-read-this-twice)
11. [Quick Cheat Sheet](#11-quick-cheat-sheet)
12. [Help! Something Broke](#12-help-something-broke)
13. [Glossary (Big Words, Simple Meaning)](#13-glossary-big-words-simple-meaning)

---

## 1. The Picture (How Everything Talks to Each Other)

Think of the project like a school project with three places where your homework lives:

```
        ┌────────────────────────┐
        │  YOUR LAPTOP           │  ← You write code here (Local by Flywheel)
        │  (the workshop)        │
        └──────────┬─────────────┘
                   │ git push / pull
                   ▼
        ┌────────────────────────┐
        │  GITHUB                │  ← The shared backpack everyone reads from
        │  consucorner-theme     │
        └──────────┬─────────────┘
                   │ git pull (Cloudways pulls from GitHub)
                   ▼
        ┌────────────────────────┐
        │  CLOUDWAYS (LIVE SITE) │  ← What your customers see
        │  https://…             │
        └────────────────────────┘
```

- **Laptop** = your private playground. Break things here all you want.
- **GitHub** = the team's shared backpack. Once code is here, the team can see it.
- **Cloudways** = the real live website. Real customers. **Be gentle.**

The path of code is always one way:

> Laptop → GitHub → Cloudways

Never edit code directly on Cloudways. **Never.**

---

## 2. Tools You Need (One Time Only)

Install these once on your Windows laptop. Pick the latest version of each one.

| Tool | Why | Link |
|------|-----|------|
| **Git for Windows** | Lets your computer talk to GitHub | https://git-scm.com/download/win |
| **GitHub Desktop** *(optional but recommended for beginners)* | Click‑buttons instead of typing scary commands | https://desktop.github.com |
| **VS Code** | The editor where you write code | https://code.visualstudio.com |
| **Local by Flywheel** | Runs WordPress on your computer | https://localwp.com |
| **A GitHub account** | Your identity in the team | https://github.com/signup |

After installing, open a terminal (PowerShell) **one time** and run:

```powershell
git config --global user.name  "Your Full Name"
git config --global user.email "your-github-email@example.com"
```

This tells Git who you are when you save changes.

> Ask the lead developer to **invite your GitHub account** to the repository `Bassiouny2/consucorner-theme`. Until you accept the invite, you cannot push code.

---

## 3. Get the Project on Your Computer

You only do this **once per laptop**. After that you just keep updating.

1. Open **Local by Flywheel** and create a new site. Call it `new-consucorner` (or whatever you like).
2. Click "Open site folder". You'll land somewhere like:

   ```
   C:\Users\<you>\Local Sites\new-consucorner\app\public\
   ```

   That folder is your WordPress site.
3. Go into `wp-content/themes/`.
4. Open PowerShell **inside that folder** (Shift + Right‑click → "Open PowerShell window here") and run:

   ```powershell
   git clone https://github.com/Bassiouny2/consucorner-theme.git consucorner
   ```

   This pulls a copy of the theme from GitHub into a folder called `consucorner`.
5. Activate the theme in WordPress:
   - In Local, click **WP Admin**.
   - Go to **Appearance → Themes** and activate **ConsuCorner**.

Done. Your local site now uses the same theme as the live site.

---

## 4. The Daily Workflow (Edit → Save → Share)

Every time you want to change something on the site, follow these 6 steps in order. It feels long the first time. After a week it will take you 2 minutes.

### Step 1 — Get the latest code

Open PowerShell inside the theme folder:

```powershell
cd "C:\Users\<you>\Local Sites\new-consucorner\app\public\wp-content\themes\consucorner"
git checkout main
git pull
```

> "I am downloading the newest version that the team made, so I do not work on old code."

### Step 2 — Make a branch (your own safe lane)

```powershell
git checkout -b feature/short-name-of-your-task
```

Examples:
- `feature/add-newsletter-popup`
- `fix/cart-button-color`
- `chore/update-readme`

> A branch is like a copy of the project that only you can mess with. The real project stays safe.

### Step 3 — Edit the code

Open the project in VS Code:

```powershell
code .
```

Edit, save, and check your changes by reloading your local site in the browser.

### Step 4 — Save your work in Git ("commit")

Every time you finish a small piece of work, save it:

```powershell
git add .
git commit -m "Short sentence about what you did"
```

Good commit message examples:
- `Fix wrong price color on cart page`
- `Add report form to My Account modal`
- `Update mobile menu spacing`

Bad commit messages (please don't):
- `update`
- `final`
- `aaaaa`

### Step 5 — Send your branch to GitHub ("push")

```powershell
git push -u origin feature/short-name-of-your-task
```

The first time on a new branch you use `-u origin <branch>`. After that just `git push`.

### Step 6 — Open a Pull Request on GitHub

See section [6. Pull Requests](#6-pull-requests-showing-your-work).

---

## 5. Branches: Working Without Breaking Things

We use **three kinds of branches**. That's it.

| Branch | What it is for | Who can change it |
|--------|----------------|-------------------|
| `main` | The branch Cloudways deploys to **live**. Always working. | Only senior devs, via Pull Requests. |
| `staging` *(optional)* | A testing copy of `main` for QA before live. | Anyone, via Pull Requests. |
| `feature/*`, `fix/*`, `chore/*` | Your personal workspaces. | You. |

**Golden rule:** never type code directly on `main`. Always make a branch first.

### Naming branches

```
feature/<short-task>     →  new feature
fix/<short-bug>          →  bug fix
chore/<short-task>       →  cleanup, docs, dependency bumps
hotfix/<short-bug>       →  urgent fix going straight to live
```

Examples: `feature/checkout-coupon-field`, `fix/mini-cart-quantity`, `chore/improve-readme`.

---

## 6. Pull Requests: Showing Your Work

A Pull Request (PR) is how you say to the team:

> "Hey, I finished something. Please look at it, and if it's good, put it into the live site."

### Steps

1. Push your branch (see Step 5 above).
2. Open https://github.com/Bassiouny2/consucorner-theme — GitHub will show a yellow banner saying "Compare & pull request". Click it.
3. Fill in:
   - **Title:** short and clear. Example: `Add Report & Support form to My Account`.
   - **Description:** answer 3 questions:
     1. What did you change?
     2. Why?
     3. How can a reviewer test it?
   - **Screenshots** of the change if it is visual. Drag and drop images straight into the box.
4. Pick **base branch = `main`** (or `staging` if your team uses it).
5. Click **Create pull request**.
6. Ask a senior developer to review it. They might:
   - **Approve and merge** → 🎉 your code is in `main`.
   - **Request changes** → fix them, push again, and the PR updates automatically.

### Do not merge your own PR until you have:

- ✅ A green checkmark (no errors)
- ✅ At least **one approval** from a senior developer
- ✅ Tested it on your local site
- ✅ Made sure the live site is not in the middle of a sale or campaign

---

## 7. Deploying to the Live Server (Cloudways + Git)

The live site lives on **Cloudways**. Cloudways already knows how to pull from GitHub. You should almost never need to do this manually — it is set up once and used by senior devs only.

### The picture (deploy version)

```
GitHub `main` branch  ─── (button click) ───►  Cloudways pulls files  ───►  Live website updated
```

### One‑time setup (already done — keep for reference)

1. In Cloudways, open the application **New ConsuCorner website (code)**.
2. Go to **Application Management → Deployment via Git**.
3. Click **Generate SSH Keys**.
4. Cloudways gives you a public key. Copy it.
5. In GitHub, go to the repo → **Settings → Deploy Keys → Add Deploy Key**:
   - Title: `Cloudways – ConsuCorner Live`
   - Paste the key. Leave "Allow write access" **unchecked**. (Cloudways only needs to read.)
6. Back in Cloudways, paste the **SSH URL** of the repo (`git@github.com:Bassiouny2/consucorner-theme.git`).
7. Set the **branch** to `main`.
8. Set the **Deployment Path** to:

   ```
   public_html/wp-content/themes/consucorner
   ```

9. Click **Start Deployment** to fetch the first time.

> If any of these steps look unfamiliar, **do not click**. Ask a senior developer. Wrong settings can wipe the theme on the live site.

### Day‑to‑day deploy (after the setup above)

Every time `main` has new code that should go live:

1. Open Cloudways → your app → **Deployment via Git**.
2. Click **Pull Latest Code**.
3. Wait for the green ✅ "Successfully deployed".
4. Open the live site and check the changed pages.

That's it. You did not FTP. You did not zip the theme. Git did the work.

### Roll back if something is wrong

If the live site looks broken after a deploy:

1. Tell the team in your group chat **right now**.
2. A senior developer will either:
   - Revert the bad commit on `main` (`git revert <commit-hash>`) and re‑deploy, or
   - Restore the previous Cloudways **backup** (Cloudways keeps daily backups).

Do not panic, do not start editing files on the server. **Tell someone first.**

---

## 8. Working with Other Developers

You said it yourself — you want senior developers to help. Here is how a small team (2–4 people) works on this theme without stepping on each other.

### Recommended team setup

| Role | What they do | Examples |
|------|--------------|----------|
| **Project owner (you)** | Decides what to build, approves designs, talks to clients. | You. |
| **Senior developer (lead)** | Reviews PRs, deploys to live, handles emergencies. | 1 person. |
| **Junior developer(s)** | Picks tasks from a board, opens PRs. | You + maybe 1 more. |
| **Designer (optional)** | Designs in Figma; hands files to devs. | Optional. |

### Workflow rules everyone follows

1. **One task = one branch = one PR.** Don't mix unrelated changes.
2. **Pull `main` first** every time you start a new task (so you don't work on old code).
3. **Small PRs are happy PRs.** A PR with 50 lines is reviewed fast. A PR with 5,000 lines is scary.
4. **Tasks live in one place.** Use GitHub Issues, Trello, or Linear — pick one and stick to it.
5. **Talk in writing.** Group chat for quick stuff, PR comments for code stuff. Don't review code in WhatsApp voice notes 🙏.
6. **Never share Cloudways or admin passwords in chat.** Use a password manager like **1Password** or **Bitwarden**.

### Daily rhythm (suggested)

| Time | What happens |
|------|---------------|
| **Morning** | Everyone pulls `main`, picks a task from the board. |
| **During the day** | Code, commit, push, open PRs. |
| **Afternoon** | Senior dev reviews PRs and merges good ones. |
| **End of week** | Senior dev pulls latest `main` on Cloudways → site is updated. |

---

## 9. Roles: Who Does What

This section is the one you forward to a new developer when they join.

### You (Project Owner / Junior Dev)

- ✅ Write small features and bug fixes in your own branch.
- ✅ Open Pull Requests with clear descriptions and screenshots.
- ✅ Test your work locally before asking for review.
- ❌ Don't merge your own PRs without approval.
- ❌ Don't push directly to `main`.
- ❌ Don't edit files on the Cloudways server.

### Senior Developer (the helper you need)

- ✅ Reviews every Pull Request.
- ✅ Merges PRs into `main` once approved.
- ✅ Clicks **Pull Latest Code** on Cloudways to deploy.
- ✅ Sets up branch protection on GitHub (so `main` is locked).
- ✅ Handles emergencies, rollbacks, and Cloudways backups.
- ✅ Mentors juniors: pair‑coding sessions, code review notes.

### How to hire / find help

If you don't have a senior developer yet, hire one **before** the next big change. Look on:

- https://www.upwork.com (filter by "Senior WordPress / WooCommerce developer")
- https://www.toptal.com (more expensive, vetted)
- https://www.linkedin.com (post a short job ad)
- Local Egyptian dev communities (Facebook groups, WUD Cairo).

Give them this file. If they can't follow the steps in here, they're not the right person.

---

## 10. Safety Rules (Read This Twice)

These rules will save your business one day.

1. **Never edit code on the live server.** Edit locally → push to GitHub → deploy.
2. **`main` is sacred.** Only senior devs merge into it, only after review.
3. **Always pull before you start.** Run `git pull` before editing anything.
4. **Commit small and often.** It is easier to undo one small mistake than ten big ones.
5. **Take a backup before every deploy.** Cloudways → **Backup & Restore → Take Backup Now**.
6. **Never delete the theme folder on Cloudways.** If you must, take a backup first.
7. **Never commit passwords, API keys, or `.env` files.** Our `.gitignore` already blocks them — keep it that way.
8. **Test on mobile.** Half of customers are on phones.
9. **Don't deploy on Fridays at 5pm.** If something breaks, everyone is gone. Deploy Monday–Thursday morning.
10. **If you don't understand something, ask.** Three minutes of asking saves three days of fixing.

---

## 11. Quick Cheat Sheet

Print this. Stick it next to your screen.

### Start a new task

```powershell
cd "C:\Users\<you>\Local Sites\new-consucorner\app\public\wp-content\themes\consucorner"
git checkout main
git pull
git checkout -b feature/your-task-name
```

### Save your progress

```powershell
git add .
git commit -m "Short clear message"
git push
```

### Open a Pull Request

Go to: https://github.com/Bassiouny2/consucorner-theme/pulls → **New pull request**.

### Update your branch with the latest `main` (if `main` moved while you worked)

```powershell
git checkout main
git pull
git checkout feature/your-task-name
git merge main
```

If you see conflicts, **stop and ask a senior dev**. Conflicts are normal but tricky for beginners.

### Deploy to live (senior dev)

Cloudways → app → **Deployment via Git → Pull Latest Code**.

---

## 12. Help! Something Broke

| Problem | What to do |
|---------|-------------|
| "I get a Git error and I don't understand it." | Take a screenshot. Send it to the senior dev. **Do not** keep clicking. |
| "My local site shows a white screen." | In `wp-config.php` set `define('WP_DEBUG', true);` to see the error. Tell the senior dev. |
| "I pushed something wrong to my branch." | That's fine — push a new commit with the fix. The PR will update. |
| "I pushed something wrong to `main`." | Tell the senior dev **now**. They can revert. |
| "The live site is broken after a deploy." | Cloudways → **Backup & Restore → Restore** the last good backup. Then tell the senior dev. |
| "I lost local changes I had not committed." | Sadly, they're usually gone. Lesson: `git commit` early and often. |

---

## 13. Glossary (Big Words, Simple Meaning)

| Word | Simple meaning |
|------|----------------|
| **Repository (repo)** | A folder of code that Git is tracking. |
| **Commit** | A saved snapshot of your work, with a message. Like a save point in a game. |
| **Push** | Send your commits up to GitHub. |
| **Pull** | Download new commits from GitHub to your computer. |
| **Branch** | A safe copy of the project where you work alone. |
| **Merge** | Mix one branch into another (usually your branch into `main`). |
| **Pull Request (PR)** | A request to merge your branch into `main`, with team review first. |
| **Deploy** | Send the latest code to the live server. |
| **Rollback** | Undo a deploy and go back to the previous version. |
| **SSH Key** | A digital ID that lets two computers trust each other without passwords. |
| **Cloudways** | The hosting company that runs the live site. |
| **WordPress** | The software the website is built on. |
| **Theme** | The "skin" of the website — the design, layout, and visible behavior. |

---

### Final word

Maintaining a real website is not magic — it's a checklist. Follow this guide every time, ask a senior dev when you're unsure, and you will be fine.

> Build small. Test always. Deploy slowly. Backup first.

Welcome to the team.
