# Sourced by CI before sudo/unshare. Pass only named fixture values as arguments;
# do not rely on sudo --preserve-env (some runner policies ignore that option).
isolated_env=(env -i)
for fixture_name in PATH LANG LC_ALL GITHUB_ACTIONS CI RUNNER_TEMP GITHUB_WORKSPACE \
  APP_ENV APP_KEY APP_DEBUG DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD DB_SOCKET TEST_MYSQL_SOCKET \
  BCRYPT_ROUNDS CACHE_DRIVER CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION MAIL_MAILER LOG_CHANNEL \
  STRIPE_KEY STRIPE_SECRET STRIPE_PUBLIC STRIPE_PRICE_ID STRIPE_WEBHOOK_SECRET \
  SAFE_TESTS_MYSQL SAFE_BROWSER_CI SAFE_HTTP_E2E BROWSER_FIXTURE_DIR PLAYWRIGHT_BROWSERS_PATH \
  HTTP_E2E_DIR HTTP_E2E_UCRM HTTP_E2E_MYSQL HTTP_E2E_MYSQL_SOCKET; do
  if [[ -v "$fixture_name" ]]; then
    isolated_env+=("$fixture_name=${!fixture_name}")
  fi
done
unset fixture_name
