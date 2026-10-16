# Releasing Show Login

A release is a **git tag**. Pushing a tag like `v1.2.0` is what ships it:

- **Packagist** sees the tag and publishes it as version `1.2.0`. Sites using Composer get it with `composer update`.
- **GitHub Actions** (`.github/workflows/release.yml`) checks the version numbers, then creates a GitHub release with a `show-login-1.2.0.zip` for sites that install plugins by hand.

Pushing to `main` alone does **not** release anything.

---

## Pick the version number

We use [semantic versioning](https://semver.org): `MAJOR.MINOR.PATCH`.

| Change | Bump | Example |
|---|---|---|
| Bug fix, no behavior change for add-ons | PATCH | 1.2.0 → 1.2.1 |
| New feature, new hook or filter | MINOR | 1.2.1 → 1.3.0 |
| Anything that can break a site or an add-on | MAJOR | 1.3.0 → 2.0.0 |

**Hooks and filters are public API.** Other plugins build on them. Renaming or removing a hook, changing its arguments, or changing what a filter's return value means is a **MAJOR** change.

---

## Release checklist

### 1. Start a release branch from `main`

Every change goes through a pull request, because that's where the tests run.

```bash
git checkout main
git pull
git checkout -b release-1.2.0
```

### 2. Bump the version in all four places

All four must match exactly. A test (`PluginTest::testVersionNumbersMatchEverywhere`) and the release workflow both fail if they don't.

| File | What to change |
|---|---|
| `show-login.php` | `* Version: 1.2.0` in the header |
| `show-login.php` | `define('SHOW_LOGIN_VERSION', '1.2.0');` |
| `includes/class-show-login.php` | `public const VERSION = '1.2.0';` |
| `readme.txt` | `Stable tag: 1.2.0` |

While you're in `readme.txt`, update `Tested up to:` to the newest WordPress version CI tested against.

### 3. Write the changelog

Add a `1.2.0` entry at the top of the changelog in **both**:

- `README.md` → `## Changelog`
- `readme.txt` → `== Changelog ==`

Write it for site owners, not developers: what changed for them. Call out anything breaking.

### 4. Run every check locally

```bash
composer test            # PHP tests
composer test:coverage   # PHP tests + fails under 90% coverage (needs pcov or xdebug)
npm run test:coverage    # JS tests + fails under 90% coverage
composer cs-check        # coding standards
bin/check-package.sh     # only plugin files are in the package
```

(First time? See **Developers** in `README.md` for setup.)

### 5. Open a pull request and merge it

```bash
git add -A
git commit -m "Release 1.2.0"
git push -u origin release-1.2.0
gh pr create --title "Release 1.2.0" --body "Version bump and changelog."
```

Wait for every check on the PR to go green, then merge it on GitHub.

### 6. Tag and push the tag

```bash
git checkout main
git pull
git tag -a v1.2.0 -m "Show Login 1.2.0"
git push origin v1.2.0
```

Always use the `v` prefix and all three numbers (`v1.2.0`, not `1.2` or `v1.2`).

### 7. Check it shipped

- **GitHub → Actions → Release** finished green.
- **GitHub → Releases** shows `v1.2.0` with the zip attached.
- **[packagist.org/packages/cartpauj/show-login](https://packagist.org/packages/cartpauj/show-login)** lists `1.2.0`. This usually takes a minute. If it doesn't appear, click **Update** on the Packagist page.
- Optional: `composer show cartpauj/show-login --all` shows the new version.

### 8. Tell the people who use it

Let anyone using it know the new version is out. Composer sites update with:

```bash
composer update cartpauj/show-login
```

---

## If something goes wrong

**The release workflow says the versions don't match.** You missed one of the four places in step 2. Delete the tag (it hasn't shipped a zip yet), fix the version, commit, and tag again:

```bash
git tag -d v1.2.0
git push origin :refs/tags/v1.2.0
# fix it in a new PR, merge, then repeat step 6
```

**A bad release already went out.** Don't move or delete the tag. Packagist and anyone who already installed it would get confused. Fix the problem and ship the next PATCH version (`1.2.1`).

---

## One-time setup (already done? skip this)

1. **Packagist:** sign in at [packagist.org](https://packagist.org) with GitHub, click **Submit**, and enter `https://github.com/cartpauj/show-login`.
2. **Auto-update:** on the package's Packagist page, make sure there's no "not auto-updated" warning. Signing in with GitHub normally sets up the hook. If not, follow Packagist's instructions to add the GitHub webhook.
3. **First tag:** Packagist only lists tagged versions, so it shows nothing until the first release tag is pushed.
