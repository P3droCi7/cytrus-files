# Cytrus Files

Prywatna aplikacja do przesyłania i pobierania plików na Twoim serwerze (Mikrus / "cytrus"), zastępująca ręczne korzystanie z FTP panelem webowym z logowaniem, kontami dla znajomych i linkami do udostępniania.

Wymagania: **PHP 8.4** z rozszerzeniami `pdo_sqlite`, `fileinfo`, `session`, `mbstring` (standardowo włączone w większości dystrybucji PHP).

> ⚠️ **Ważne o `ftp/`:** usługa Cytrus z definicji zawsze udostępnia zawartość folderu `ftp/` publicznie przez HTTP (to reklamowana funkcja — "dostęp do serwera za pomocą współdzielonego serwera FTP"), **niezależnie od logowania, uprawnień czy `.htaccess`**. Dlatego aplikacja NIE przechowuje tam plików użytkowników — magazynem jest osobny folder [`storage/`](cytrus/storage), który faktycznie respektuje `.htaccess` na tym hostingu. Plik w `ftp/` wrzucony starym klientem FTP i plik wgrany przez panel webowy to od teraz dwa niezależne zbiory.

## Co zawiera projekt

```
cytrus/
  index.php            <- jedyny publiczny plik wejściowy (front controller)
  config.php           <- konfiguracja aplikacji (edytuj przed wdrożeniem)
  storage/              <- prywatny magazyn plików aplikacji (realnie chroniony .htaccess)
  ftp/                  <- Twój stary folder FTP - Cytrus serwuje go PUBLICZNIE, appka go nie używa
  data/                 <- baza danych SQLite (użytkownicy, linki, dziennik zdarzeń) - NIE commitować
  app/                   <- logika aplikacji (auth, pliki, CSRF, baza danych...)
  controllers/           <- kontrolery obsługujące poszczególne akcje
  views/                 <- szablony HTML
  assets/                <- CSS, JS (upload fragmentaryczny)
  bin/console.php        <- narzędzie CLI (tworzenie administratora z SSH)
  .htaccess, */.htaccess <- blokady dostępu (realnie działają wszędzie poza ftp/)
```

Twój obecny `ftp/` zostaje nietknięty — aplikacja go nie rusza, bo i tak nie da się go realnie zabezpieczyć na tym hostingu.

## 1. Wgranie na serwer

Prześlij (git, scp lub sftp) zawartość folderu `cytrus/` do `/cytrus` na serwerze, **nie nadpisując** istniejącej zawartości `ftp/`. Jeśli wysyłasz całość przez `rsync`, wyłącz nadpisywanie `ftp/`:

```bash
rsync -av --exclude 'ftp/*' cytrus/ pedro@karol202:/cytrus/
```

Istniejący plik `index.php` (19 bajtów) zostanie zastąpiony nowym front-controllerem — zrób kopię zapasową, jeśli coś tam było:

```bash
cp /cytrus/index.php /cytrus/index.php.bak
```

## 2. Uprawnienia plików

```bash
cd /cytrus
chmod 750 data app controllers views bin
chmod 640 config.php
mkdir -p data && chmod 770 data
```

PHP-FPM na Cytrusie (Mikrus) zwykle działa jako inny użytkownik niż Twoje konto SSH i jest objęty `open_basedir` ograniczonym do `/cytrus`. Jeśli po wdrożeniu dostaniesz błąd 500 o braku dostępu do plików, poluzuj uprawnienia katalogów do `755`/`770` zamiast `750`/`700` — pełna procedura diagnostyczna jest opisana w historii commitów tego repo (permission denied → open_basedir → data dir).

```bash
sudo chown -R cytrus:cytrus /cytrus/data /cytrus/storage   # jeśli masz do tego uprawnienia
```

## 3. KRYTYCZNE: `ftp/` jest ZAWSZE publiczny — appka go nie używa

Cytrus to usługa nginx, która **celowo** serwuje zawartość folderu `ftp/` publicznie przez HTTP niezależnie od `.htaccess`, logowania czy uprawnień — to jest jej reklamowana funkcja ("dostęp do serwera za pomocą współdzielonego serwera FTP"), nie błąd konfiguracji, i nie da się tego wyłączyć z poziomu użytkownika.

Dlatego magazynem plików aplikacji jest **`storage/`**, nie `ftp/`. Na tym hostingu `.htaccess` (`Require all denied`) **faktycznie działa** dla wszystkich pozostałych katalogów (`app/`, `controllers/`, `views/`, `data/`, `bin/`, `storage/`) — potwierdzone w praktyce (bezpośrednie żądania do tych ścieżek zwracają 403).

Po wdrożeniu **koniecznie przetestuj**:
```bash
curl -I https://twojadomena/storage/jakikolwiek_plik   # oczekiwane: 403
curl -I https://twojadomena/data/app.sqlite            # oczekiwane: 403
curl -I https://twojadomena/ftp/cokolwiek               # to będzie 200 - i tak ma być, appka tam nie pisze
```

## 4. Pierwsze uruchomienie

Otwórz `https://twojadomena/cytrus/index.php` (lub `index.php` jeśli to katalog główny domeny) — zobaczysz kreator pierwszego konta administratora (`?p=setup`). Trasa ta jest automatycznie blokowana, gdy tylko istnieje choć jeden użytkownik.

Alternatywnie, z SSH:

```bash
cd /cytrus
php bin/console.php create-admin pedro "twoje-bardzo-dlugie-haslo"
```

## 5. Tworzenie kont dla znajomych

Zaloguj się jako administrator → **Użytkownicy** → utwórz konto, ustaw uprawnienia:
- *może przesyłać pliki* — upload i tworzenie folderów,
- *może usuwać pliki*,
- *może tworzyć linki do pobrania* (udostępnianie bez logowania znajomym spoza systemu).

Dla znajomych, którym chcesz dać tylko pobieranie, wystarczy nie zaznaczać żadnego z uprawnień zapisu — będą mogli jedynie przeglądać i pobierać pliki po zalogowaniu, albo możesz wysłać im bezpośredni **link udostępniający** (zakładka „Udostępnione linki”) z opcjonalnym hasłem, terminem ważności i limitem pobrań — bez zakładania im konta.

## 6. Bezpieczeństwo wbudowane w aplikację

- Hasła haszowane `password_hash()` (Argon2id/bcrypt w zależności od PHP), nigdy nie przechowywane jawnie.
- Ochrona przed brute-force: blokada logowania po kilku nieudanych próbach (adres IP + login).
- Tokeny CSRF przy każdej akcji zmieniającej stan (upload, usuwanie, zmiana nazwy, tworzenie linków, zarządzanie użytkownikami).
- Ciasteczko sesji: `HttpOnly`, `SameSite=Lax`, `Secure` automatycznie po wykryciu HTTPS.
- Ochrona przed path traversal — każda ścieżka pliku jest weryfikowana względem katalogu `ftp/`, niemożliwe jest wyjście poza niego (`../`).
- Blokada przesyłania plików wykonywalnych (`.php`, `.sh`, `.exe` itd.) oraz wymuszenie, że katalog `ftp/` nigdy nie wykonuje skryptów.
- Pobieranie z obsługą `Range` (wznawianie dużych plików).
- Dziennik zdarzeń (logowania, uploady, usunięcia, tworzenie linków) widoczny w panelu administratora.

## 7. Włączenie HTTPS (zalecane)

Gdy skonfigurujesz certyfikat TLS (np. Let's Encrypt), ustaw w `config.php`:

```php
'force_https' => true,
```

Wymusi to `Strict-Transport-Security` i bezpieczne ciasteczka nawet jeśli serwer nie ustawia `$_SERVER['HTTPS']` poprawnie za reverse proxy.

## 8. Kopie zapasowe

Regularnie archiwizuj `data/app.sqlite` (konta, linki, dziennik) — to jedyny stan aplikacji poza samymi plikami w `ftp/`.

## 9. Automatyczny deploy (GitHub Actions)

Repozytorium zawiera gotowy workflow [.github/workflows/deploy.yml](.github/workflows/deploy.yml): każdy `push` na `main` w ścieżce `cytrus/**` sprawdza składnię PHP (`php -l`), synchronizuje pliki na serwer przez `rsync` (z pominięciem `ftp/` i `data/`), poprawia uprawnienia i robi health-check pod `HEALTHCHECK_URL`. Darmowe minuty GitHub Actions dla prywatnych repo w zupełności wystarczą na tak mały i rzadki deploy.

### Jednorazowa konfiguracja

1. **Zainicjuj repo i wypchnij do prywatnego GitHuba:**
   ```bash
   cd /Users/pedro/Desktop/projects/file_browser_mikrus
   git init
   git add .
   git commit -m "Initial commit: Cytrus Files"
   git branch -M main
   git remote add origin git@github.com:<twoj-user>/cytrus-files.git
   git push -u origin main
   ```

2. **Wygeneruj dedykowany klucz SSH tylko do deployu** (nie używaj swojego osobistego klucza):
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/cytrus_deploy -N "" -C "github-actions-deploy"
   ```
   Dodaj klucz publiczny na serwerze:
   ```bash
   ssh pedro@<host> -p <port> 'cat >> ~/.ssh/authorized_keys' < ~/.ssh/cytrus_deploy.pub
   ```

3. **Dodaj sekrety repozytorium** (Settings → Secrets and variables → Actions → New repository secret):
   - `DEPLOY_SSH_KEY` — zawartość `~/.ssh/cytrus_deploy` (klucz **prywatny**)
   - `DEPLOY_HOST` — adres serwera (np. `serwerXXX.mikrus.net`)
   - `DEPLOY_PORT` — port SSH
   - `DEPLOY_USER` — `pedro`
   - `DEPLOY_PATH` — `/cytrus`
   - `HEALTHCHECK_URL` — `https://pedro.cytr.us/`

   Dla kroku health-check warto też utworzyć environment `production` (Settings → Environments) z tymi samymi sekretami, ewentualnie z wymaganiem ręcznej akceptacji przed deployem.

4. Od teraz każdy `push` na `main` automatycznie wdraża zmiany. Podgląd przebiegu: zakładka **Actions** w GitHubie.

### Dlaczego nie Jenkins

Masz już Jenkinsa w Dockerze na tym samym serwerze co `/cytrus` — to też działałby (lokalny dostęp do plików bez SSH na zewnątrz), ale GitHub Actions nie wymaga utrzymywania dodatkowego kontenera, aktualizacji wtyczek ani webhooków, a darmowy limit minut z nawiązką pokrywa tak rzadkie i krótkie (~1 min) zadanie. Jeśli kiedyś zajdzie potrzeba trzymania sekretów wyłącznie u siebie (bez wysyłania ich do chmury GitHuba), Jenkins z `Jenkinsfile` i webhookiem z GitHuba jest gotową alternatywą — daj znać, jeśli wolisz to podejście, przygotuję wtedy `Jenkinsfile` zamiast workflow YAML.
