# Деплой (проверено на new.sofiago.eu)

## Реальная раскладка на сервере (HestiaCP)

Для каждого домена HestiaCP уже создаёт `~/web/<domain>/{public_html, private, logs, ...}`.
`private/` — готовая папка вне веб-доступа, ровно то, что нужно для `app/`, `config/`,
`database/`, `vendor/`. nginx здесь работает как фронт для Apache+PHP-FPM (`proxy_pass` на
`127.0.0.1:8080`/Apache), `AllowOverride All` включён — то есть `.htaccess` с mod_rewrite
работает из коробки, отдельный nginx-шаблон менять не пришлось.

```
~/web/<domain>/
  public_html/        # = содержимое sofiago-site/public/ (index.php, install.php, .htaccess, assets/, uploads/)
  private/            # = app/, config/, database/, vendor/, composer.json — вне веб-доступа
```

`public/index.php` и `public/install.php` сами определяют, где лежит `app/` — рядом с
`public/` (простой хостинг, локальная разработка) или в соседней `private/` (HestiaCP):

```php
$appRoot = is_dir(__DIR__ . '/../app') ? dirname(__DIR__) : dirname(__DIR__) . '/private';
```

Больше нигде этот выбор не важен — остальной код обращается к путям только через `$appRoot`
или через `__DIR__` внутри своего файла.

## Почему нет отдельного nginx-шаблона

Изначально предполагалось делать кастомный HestiaCP web-template с location-блоком под
pretty URL. По факту это не понадобилось: дефолтный шаблон домена отдаёт статику (css/js/img/…)
напрямую из `public_html` по расширению файла, а всё остальное проксирует на Apache, у которого
`AllowOverride All` — значит, `public/.htaccess` с обычным `RewriteRule ^ index.php` работает
без правки серверных шаблонов.

## Деплой (что реально было сделано)

1. Локально: `composer install` → `vendor/`.
2. Собрать два архива:
   - `public.tar.gz` — содержимое `sofiago-site/public/`.
   - `private.tar.gz` — `app/`, `database/`, `vendor/`, `composer.json`, `composer.lock` и
     **реальный** `config/config.php` (не `config.example.php!`) с настоящими данными БД/SMTP
     и `base_url` вида `https://new.sofiago.eu`.
3. Залить оба архива по SFTP во временную папку и распаковать по SSH:
   ```bash
   tar xzf public.tar.gz  -C ~/web/<domain>/public_html
   tar xzf private.tar.gz -C ~/web/<domain>/private
   ```
4. Один раз поднять схему БД. Раз SSH-доступ уже есть — проще выполнить `schema.sql` прямо
   через `mariadb`, без похода через HTTP:
   ```bash
   mariadb -u sofiago_main -p sofiago_main < database/schema.sql
   ```
   **Важно:** если пароль от БД содержит `#`, `;` или пробел — при передаче через
   `--defaults-extra-file`/`.my.cnf` его нужно взять в кавычки (`password="...#..."`),
   иначе всё после `#` MySQL воспримет как начало комментария и обрежет пароль — ловили эту
   ошибку именно на этом пароле (`Access denied ... using password: YES` при формально верном
   пароле).

   `database/install.php` + `public/install.php` (curl-эндпоинт с токеном) в проекте
   остаются — это штатный способ поднять схему там, где прямого SSH/консоли нет (или для
   Олега, чтобы разворачивать самостоятельно без необходимости давать доступ). Токен
   генерируется случайно на каждый деплой и не хранится в репозитории.

## Проверка после деплоя

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/            # 200, есть категории
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/about       # 200
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/nope        # 404 (наш errors/404.tpl.php)
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/assets/theme/css/style.css  # 200, статика напрямую
```

Всё это уже проверено на `https://new.sofiago.eu/` — работает.

## Ночной cron (истечение объявлений)

`private/cron/midnight.php` не запускается сам по себе — его нужно один раз добавить в
crontab (`crontab -e` под пользователем сайта, `sofiago`). Рекомендуемое время — 02:00 по
Софии (тихий час, не обязательно ровно полночь несмотря на имя файла):

```
0 2 * * * /usr/bin/php /home/sofiago/web/<domain>/private/cron/midnight.php >> /home/sofiago/web/<domain>/private/cron/midnight.log 2>&1
```

Файл лежит в `private/` (не `public_html/`) намеренно — HTTP туда не достаёт в принципе, это не
завязано на дополнительную защиту типа .htaccess. Сам скрипт при этом ещё и сам проверяет
`PHP_SAPI === 'cli'` как самостраховку. Безопасно гонять хоть каждую ночь: обе операции
идемпотентны (просроченные объявления не трогаются повторно; письмо шлётся максимум один раз
на окно — флаг `expiry_notified_at`, при неудаче отправки не выставляется и попытка повторяется
автоматически на следующую ночь).

## Перенос с new.sofiago.eu на sofiago.eu в конце разработки

`new.sofiago.eu` — временный тестовый поддомен (реальный сервер, реальная БД
`sofiago_main`/`sofiago_main`). Когда разработка закончится:

1. Сменить в `config/config.php` на проде `app.base_url` → `https://sofiago.eu`,
   `session.domain` → `sofiago.eu`, `app.env` → `production` (отключает вывод ошибок),
   `app.allow_indexing` → `true` (иначе `robots.txt` продолжит запрещать индексацию всего
   сайта — это специально включено на `new.sofiago.eu`, чтобы Google не проиндексировал
   тестовые demo-объявления).
2. Перенести содержимое `public_html`/`private` на домен `sofiago.eu` (или просто
   переключить document root/поддомен — уточнить у Олега, как ему удобнее в моменте).
3. Сменить пароли БД/FTP/SSH, которые лежали в `credentials.txt` для периода разработки
   (сам файл в репозиторий не входит и хранится только локально у Олега).
4. Прогнать проверочные curl-запросы выше уже на `sofiago.eu`.
