# Terv – GitHub repository szinkronizáló (SportMate felvételi feladat)

> Ez egy közösen szerkesztett munkaterv. A pontok közül bármelyiket pontosíthatjuk
> vagy törölhetjük – a cél, hogy a tényleges kódolás előtt lépésről lépésre
> végigbeszéljük a megközelítést.

## 0. Kiinduló állapot (már megvan)

- Friss Laravel 13 + Inertia.js v3 + Vue 3 starter kit telepítve, Fortify-os
  authentikációval (regisztráció, login, profil, jelszó, stb. már működik).
- SQLite adatbázis, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`.
- Pest teszt keretrendszer, Pint, Larastan, Laravel Wayfinder (TS route/action
  generálás), shadcn-vue alapú UI komponensek (`resources/js/components/ui`).
- A feladat UI-ja a meglévő auth mögé kerül (a `dashboard` route mintájára),
  tehát bejelentkezett felhasználó látja a szinkronizációs felületet.

**Döntés:** a `sync_targets` és a `repositories` **globálisan, userek
között megosztva** tárolódnak – egy GitHub user/org csak egyszer
szinkronizálódik, függetlenül attól, hány Laravel felhasználó adta hozzá.
A láthatóságot egy **kapcsoló (pivot) tábla** (`sync_target_user`)
határozza meg: ez köti össze, melyik Laravel felhasználóhoz melyik
sync target tartozik, azaz kinek mi jelenik meg a listájában. Ez
elkerüli a repó-adatok duplikált tárolását (l. 1. pont), ami a README 3. pontjának ("prevent duplicates with an appropriate database
constraint") szorosabb értelmezése.

**Megfontolt, de elvetett alternatíva:** felmerült egy egyszerűbb,
user-enkénti modell is, ahol a `sync_targets` táblán lenne egy
`user_id` oszlop, kapcsoló tábla nélkül. Ez kevesebb fejlesztési időt
igényelne (nincs pivot tábla, nincs versenyhelyzet-kezelés két
felhasználó egyidejű hozzáadása között), viszont elveszítené a
globális katalógus fő előnyét: ha két felhasználó ugyanazt a GitHub
user/org-ot követi, a repóik duplán tárolódnának, ahelyett hogy egyszer,
megosztva szerepelnének. Mivel a cél kifejezetten az, hogy a README 3.
pontjának dedup-elvárását a lehető legszorosabban értelmezzük, és a
globális katalógus jobban bemutatja az adatmodellezési és
versenyhelyzet-kezelési képességeket, **a globális katalógus + pivot
tábla megoldás mellett döntöttünk**.

**Döntés:** a fejlesztés a repository pattern-t követi – a controllerek és
service-ek sosem hívnak közvetlenül Eloquent query-t, mindig egy dedikált
repository osztályon (interfészen) keresztül érik el az adatokat (l. 2.
pont).

---

## 1. Adatbázis terv

### 1.1 `sync_targets` tábla (globális katalógus)

| Oszlop                     | Típus                                                   | Megjegyzés                                                     |
| -------------------------- | ------------------------------------------------------- | -------------------------------------------------------------- |
| `id`                       | bigint pk                                               |                                                                |
| `name`                     | string                                                  | GitHub username vagy organization név                          |
| `type`                     | string/enum (`user`, `organization`)                    | melyik GitHub API végpontot hívjuk                             |
| `status`                   | string/enum (`pending`, `syncing`, `success`, `failed`) | szinkronizáció állapota                                        |
| `last_synced_at`           | timestamp, nullable                                     | utolsó **sikeres** szinkron időpontja                          |
| `last_sync_error`          | text, nullable                                          | utolsó hiba üzenete (ember által olvasható, nem raw exception) |
| `created_at`, `updated_at` | timestamp                                               |                                                                |

Nincs `user_id` oszlop ezen a táblán – a target nem egy userhez tartozik,
hanem egy globálisan egyedi entitás ("ez a GitHub user/org, amit
valaki(k) követnek"). A szinkron állapota (`status`, `last_synced_at`,
`last_sync_error`) is megosztott: ha a szinkron sikertelen, minden user,
aki ezt a targetet követi, ugyanazt a hibát látja – ez helyes, mert a
mögötte lévő GitHub hívás is ugyanaz mindenkinek.

**Egyediség:** unique constraint `(name, type)` – ugyanaz a GitHub
user/org csak egyszer szerepelhet a táblában, bárki is adta hozzá
elsőként.

**Indexek:** `status` (lista szűréshez/dashboardhoz), `(name, type)`
unique index egyben indexként is szolgál.

### 1.2 `sync_target_user` tábla (kapcsoló/pivot tábla)

| Oszlop                     | Típus                                         | Megjegyzés                     |
| -------------------------- | --------------------------------------------- | ------------------------------ |
| `id`                       | bigint pk                                     |                                |
| `user_id`                  | FK → `users.id`, `onDelete('cascade')`        | melyik Laravel user adta hozzá |
| `sync_target_id`           | FK → `sync_targets.id`, `onDelete('cascade')` | melyik globális targetet       |
| `created_at`, `updated_at` | timestamp                                     | mikor adta hozzá a user        |

Ez a tábla dönti el, **kinek mi jelenik meg**: egy user csak azokat a
targeteket (és azok repóit) látja, amikhez saját maga hozzáadott egy sor
ezen a táblán keresztül. Ha egy user felvesz egy már létező targetet
(mert más már hozzáadta), csak egy új pivot sor jön létre, maga a
target és a repói nem duplikálódnak.

**Egyediség:** unique constraint `(user_id, sync_target_id)` – egy user
ne tudja kétszer hozzáadni ugyanazt a targetet.

**Indexek:** `user_id` (a user listája ezzel kérdeződik le), a
`sync_target_id` oldali lookup (pl. "ki mindenki követi ezt a targetet")
a unique indexen belül is elérhető, FK-k amúgy is indexelve vannak.

### 1.3 `repositories` tábla

| Oszlop                     | Típus                                         | Megjegyzés                             |
| -------------------------- | --------------------------------------------- | -------------------------------------- |
| `id`                       | bigint pk                                     |                                        |
| `sync_target_id`           | FK → `sync_targets.id`, `onDelete('cascade')` |                                        |
| `github_id`                | bigint unsigned                               | GitHub oldali repo ID                  |
| `name`                     | string                                        |                                        |
| `full_name`                | string                                        | pl. `owner/repo`                       |
| `description`              | text, nullable                                |                                        |
| `url`                      | string                                        | repo HTML URL                          |
| `language`                 | string, nullable                              | fő nyelv                               |
| `stargazers_count`         | unsigned int                                  |                                        |
| `open_issues_count`        | unsigned int                                  |                                        |
| `is_archived`              | boolean                                       |                                        |
| `github_updated_at`        | timestamp                                     | GitHub oldali `updated_at`/`pushed_at` |
| `created_at`, `updated_at` | timestamp                                     | lokális audit                          |

**Egyediség:** unique constraint `(sync_target_id, github_id)` – mivel a
`sync_target` maga is globálisan egyedi (1.1 pont), ez most **valódi,
teljes dedupot** jelent: egy adott GitHub repo pontosan egyszer
szerepel a táblában, függetlenül attól, hány user követi a targetet.
Upsert ez alapján történik (`updateOrCreate` / `upsert`).

**Indexek:**

- `(sync_target_id, github_id)` unique – ez a dedup kulcs.
- `sync_target_id` (lista lekéréshez, FK index amúgy is létrejön).
- `stargazers_count`, `open_issues_count`, `github_updated_at` – ezek a
  leendő rendezési/szűrési oszlopok, érdemes indexelni, ha a tábla nagyra
  nő. Rövid jegyzet lesz róla a kódban/README-ben, hogy éles rendszerben
  ezekre compound index kellene a leggyakoribb szűrés+rendezés
  kombinációhoz.
- Full-text kereséshez (`name`, `description`) SQLite-on egyszerű `LIKE`
  lesz a baseline-ban; megjegyzés, hogy production DB-n (MySQL/Postgres)
  ezt full-text indexszel vagy Laravel Scout-tal váltanánk ki.

### 1.4 Verseny-helyzet két egyidejű "hozzáadás" között

Ha két user majdnem egyszerre adja hozzá ugyanazt a GitHub user/org-ot,
mindketten egy "find or create" műveletet futtatnak a `sync_targets`
táblán. Ezt tranzakcióban + a unique constraintre hagyatkozva kezeljük:
a második "create" a unique constraint miatt hibát dob, amit elkapunk,
és helyette a közben létrejött sort olvassuk ki, majd arra fűzzük fel a
pivot sort. Ez egy rövid jegyzet/komment szintű kezelés lesz a baseline
kódban (`firstOrCreate` + `catch (UniqueConstraintViolationException)`
retry), nem egy bonyolult locking mechanizmus.

### 1.5 Migrációk

- `create_sync_targets_table`
- `create_sync_target_user_table`
- `create_repositories_table`

### 1.6 Modellek – elnevezési döntés

A repository pattern miatt (l. 2. pont) a "repository" szó két dolgot is
jelentene: a GitHub repót és az adatelérési osztályt. Az ütközés
elkerülésére az Eloquent modell neve **`GithubRepository`** lesz (a
tábla neve marad `repositories`, `protected $table = 'repositories';`).
Így az adatelérési réteg osztályai (`SyncTargetRepository`,
`GithubRepositoryRepository`) egyértelműen a Laravel "repository
pattern" konvenciót követik, nem keverednek a modell nevével.

- `App\Models\SyncTarget` – `users()` belongsToMany (a `sync_target_user`
  pivot táblán keresztül), `repositories()` hasMany (a kapcsolat neve
  maradhat `repositories`, csak az osztálynév más).
- `App\Models\GithubRepository` – `syncTarget()` belongsTo.
- `App\Models\User` – `syncTargets()` belongsToMany (ugyanazon a pivot
  táblán keresztül).
- Enumok: `App\Enums\SyncTargetType` (`User`, `Organization`),
  `App\Enums\SyncStatus` (`Pending`, `Syncing`, `Success`, `Failed`) –
  PHP natív enum, Laravel cast.

---

## 2. Adatelérési réteg – repository pattern

Cél: a controllerek, service-ek és job-ok sosem hívnak közvetlenül
`SyncTarget::` / `GithubRepository::` statikus Eloquent query-t, hanem
egy interfészen keresztül kérik el/mentik az adatot. Ez egyrészt a
README "responsibilities reasonably separated" elvárását szolgálja,
másrészt a service-ek unit tesztjeit egyszerűbbé teszi (interfész
mockolható, nem kell valódi DB-t ütni, ha csak a logikát teszteljük).

- `app/Repositories/Contracts/SyncTargetRepositoryInterface.php`
    - `allForUser(User $user): Collection` – a user pivot kapcsolatán
      keresztül listázza a targetjeit.
    - `find(int $id): ?SyncTarget` – egyszerű lekérdezés id alapján,
      `null`, ha nincs ilyen sor. **Nem** dönt jogosultságról – ki
      férhet hozzá az adott targethez, azt a `SyncTargetPolicy` dönti el
      a controllerben, hogy a jogosultsági logika ne keveredjen az
      adatelérési rétegbe.
    - `findOrCreateAndAttachToUser(User $user, string $name, SyncTargetType $type): SyncTarget`
      – megkeresi a globális targetet `(name, type)` alapján; ha nincs,
      létrehozza; majd `firstOrCreate`-del felveszi a pivot sort a
      userhez (ha még nincs). Ez a "hozzáadás" egyetlen belépési pontja.
    - `markAsSyncing(SyncTarget $target): bool` – **atomi, feltételes
      update**: egyetlen adatbázis-művelettel próbálja `syncing`-re
      állítani a target sort, de **csak akkor**, ha annak jelenlegi
      állapota még nem `syncing` (pl.
      `SyncTarget::where('id', $target->id)->where('status', '!=',
SyncStatus::Syncing)->update(['status' => SyncStatus::Syncing])`
      és a módosított sorok száma alapján `true`/`false`). Ez zárja ki,
      hogy két egyidejű kérés közül mindkettő elindítsa a szinkront (l. 5. pont) – nem egy előzetes olvasás + utólagos írás, hanem
      egyetlen oszthatatlan lépés.
    - `markAsSynced(SyncTarget $target, Carbon $at): void`
    - `markAsFailed(SyncTarget $target, string $error): void`
- `app/Repositories/Contracts/GithubRepositoryRepositoryInterface.php`
    - `paginateForTarget(SyncTarget $target, array $filters): LengthAwarePaginator`
      (ez implementálja a keresést/szűrést/rendezést/lapozást egy helyen)
    - `upsertMany(SyncTarget $target, array $githubRepositoryDataList): void`
- Implementációk: `app/Repositories/EloquentSyncTargetRepository.php`,
  `app/Repositories/EloquentGithubRepositoryRepository.php` – ezek
  tartalmazzák a tényleges Eloquent/query builder hívásokat, és az 1.4
  pontban leírt race condition kezelést is (`findOrCreateAndAttachToUser`).
- Bindolás: `App\Providers\RepositoryServiceProvider` (vagy a meglévő
  `AppServiceProvider`-ben) köti össze az interfészeket a konkrét
  implementációkkal (`$this->app->bind(SyncTargetRepositoryInterface::class,
EloquentSyncTargetRepository::class)`), így a controllerek/service-ek
  mindig az interfészt kapják dependency injectionnel.
- A `RepositorySynchronizer` (l. 4. pont) és a controllerek (l. 6. pont)
  csak ezeket az interfészeket ismerik, az `App\Models\*` Eloquent
  osztályokat csak a repository implementációk érik el közvetlenül.

---

## 3. GitHub integrációs réteg

- `app/Services/GitHub/GitHubClient.php` – dedikált kliens osztály, ami a
  Laravel `Http` facade-ot használja, felelős:
    - `getRepositoriesFor(SyncTarget $target): Generator|array` – a
      megfelelő végpont hívása (`/users/{user}/repos` vagy
      `/orgs/{org}/repos`) a `type` alapján.
    - Pagination kezelése (`per_page=100`, `page` növelése amíg van
      következő oldal / `Link` header `rel="next"` figyelése) – a teljes
      többoldalas bejárás megvalósítása a célunk, a kódban a ciklus
      eleve erre készül.
    - Hibakezelés: 404 (nincs ilyen user/org), 403/rate limit, 5xx, timeout
      – ezeket dedikált exception típusokra fordítjuk le
      (`GitHubTargetNotFoundException`, `GitHubRateLimitException`,
      `GitHubApiException`), amiket a hívó kód (sync service) elkap és
      emberi hibaüzenetre fordít.
    - `GitHubRateLimitException` a GitHub válasz `Retry-After` (vagy
      `X-RateLimit-Reset`) headerét is átadja, hogy a logban/hibaüzenetben
      szerepeljen, mikor érdemes újra próbálkozni – maga az automatikus
      újrapróbálkozás a job retry/backoff jegyzet szintű része (l. 5. pont).
    - Opcionális `GITHUB_TOKEN` env változó használata (hitelesített hívás
      nagyobb rate limit-tel) – ha nincs beállítva, anonim hívás megy.
- `config/services.php`-ba felvesszük a `github` kulcsot
  (`base_url`, `token`), és a `.env.example`-t kiegészítjük a
  `GITHUB_TOKEN=` sorral (üresen hagyva, hogy jelezze: opcionális).
- A kliens **nem** tud semmit a lokális modellekről – tiszta határ a
  külső API és az app között (ezért van rá külön DTO, l. 4. pont).

---

## 4. Szinkronizációs folyamat (service/action réteg)

- `app/DataTransferObjects/GitHubRepositoryData.php` (vagy egyszerű
  readonly DTO/Value Object) – a GitHub JSON válasz leképezése egy
  tipizált objektumra, hogy a mapping logika egy helyen legyen, és a
  kliens ne adjon vissza nyers array-t/Eloquent-et.
- `app/Services/RepositorySynchronizer.php` – ez a tényleges
  "szinkronizáló" osztály, amely a 2. pontban leírt repository
  interfészeket kapja konstruktorban (nem Eloquent modelleket). Mivel a
  `sync_targets` globális, a synchronizer sosem user-specifikus – egy
  targetet szinkronizál, függetlenül attól, hányan követik:
    1. Lekéri a repókat a `GitHubClient`-en keresztül (ez egy külső HTTP
       hívás, **tranzakción kívül** történik – l. lejjebb a tranzakció-
       határokról szóló jegyzetet).
    2. A DTO listát a `GithubRepositoryRepositoryInterface::upsertMany()`
       hívásra adja át (a `sync_target_id` + `github_id` kulcs alapján
       upsertel), majd a `SyncTargetRepositoryInterface::markAsSynced()`
       hívással frissíti a target állapotát. Ez a két lépés **egy közös
       adatbázis-tranzakción belül** fut (`DB::transaction(function () {
... })`), hogy a repók mentése és a target "sikeres" állapotba
       állítása együtt sikerüljön vagy együtt bukjon el – ha a folyamat a
       kettő között szakadna meg, a tranzakció visszagördül, és a target
       állapota a következő próbálkozásig a korábbi (`syncing`) marad,
       nem áll elő olyan állapot, hogy a repók már frissültek, de a
       target state-je nem.
    3. Hiba esetén elkapja a GitHub kliens exceptionjeit, logolja
       (`Log::error` kontextussal: target id, GitHub hívás, HTTP status),
       és a usernek szánt rövid, érthető hibaszöveget ment el
       `markAsFailed()`-en keresztül. Ez egyetlen sor update, nem igényel
       külön tranzakciót.
    - A `markAsSyncing()` (a job legelső lépése, l. 5. pont) **nem** része
      ennek a tranzakciónak: az egy önálló, atomi feltételes update (l. 2.
      pont), aminek pontosan az a célja, hogy a GitHub-hívás _előtt_,
      külön lépésben, azonnal commitolódjon – így más kérések rögtön
      látják a `syncing` állapotot, amíg a (hosszabb ideig tartó) GitHub
      hívás folyik. Tudatosan nem tartjuk nyitva a tranzakciót a teljes
      GitHub HTTP hívás idejére: egy külső hálózati hívást tranzakción
      belül tartani feleslegesen hosszan zárolná az érintett sorokat.
- Ez a réteg controller- és job-független, és mivel csak interfészeket
  ismer, unit tesztelhető úgy is, hogy a repository interfészeket
  mockoljuk (nincs szükség valódi DB-re a tiszta logika teszteléséhez),
  illetve feature szinten `Http::fake()`-kel a teljes folyamatra.

---

## 5. Queue-s szinkronizáció

- `app/Jobs/SyncRepositoriesJob.php` – implementálja
  `ShouldQueue`, kap egy `SyncTarget $target`-et (vagy ID-t, hogy mindig
  friss modellt töltsünk be).
    - A job indulásakor a `RepositorySynchronizer`-en keresztül
      `syncing`-ra állítja a targetet.
    - Meghívja a `RepositorySynchronizer`-t.
    - Siker/hiba esetén frissíti a target állapotát (ezt már a
      synchronizer is megteheti, de a job felel a `syncing` állapotért és
      a végső commit/rollback-ért).
- **Duplikált kérés elutasítása – atomi megoldás:**
  mivel egy targetet több user is követhet, jóval valószínűbb, hogy két
  user (egymástól függetlenül) **egyszerre** indít szinkront ugyanarra
  a targetre. Egy "előbb megnézem az állapotot, utána eldöntöm, hogy
  dispatcheljek-e" jellegű, két lépésből álló ellenőrzés önmagában nem
  biztonságos: két egyidejű kérés mindkettő ugyanazt az állapotot
  olvashatja, mielőtt bármelyik is módosítaná azt, így mindkettő
  elindíthatja a job-ot. Ezért a `SyncTargetSyncController` **nem**
  külön lépésben olvassa ki, majd állítja be az állapotot, hanem
  közvetlenül meghívja a `SyncTargetRepositoryInterface::markAsSyncing()`
  (l. 2. pont) atomi, feltételes update műveletét: ha az `true`-t ad
  vissza (tényleg ő állította `syncing`-re a sort), dispatcheli a
  job-ot; ha `false`-t (mert a sor már `syncing` volt), elutasítja a
  kérést, és erről visszajelzést ad a felhasználónak. Ezzel a
  duplikáció-elleni védelem magában az adatbázis-műveletben van
  kikényszerítve, nem a PHP kód két külön lépésének sorrendjében.
- **Dedikált megjegyzések (nem feltétlen implementáció, kommentben/README-ben
  kifejtve, a feladat ezt is engedi):**
    - _Job szintű dedup/overlap_: `ShouldBeUnique` + `WithoutOverlapping`
      middleware (`uniqueId()` = target ID), hogy a queue worker szinten
      is garantált legyen, hogy egy targetre egyszerre csak egy job fusson
      (a controller szintű elutasítás mellett, védőhálóként).
    - _Retry/failed jobs_: `$tries`, `backoff()` beállítás, `failed()`
      hook, ami a target-et `failed` állapotba teszi és elmenti a hibát;
      `failed_jobs` tábla már megvan a starter kitből.
    - _Timeout_: `$timeout` property a job-on + GitHub HTTP kliensen
      `Http::timeout()`.
- Baseline-ban tényleg implementáljuk: a job léte, státuszváltás,
  hibakezelés, a fent leírt **atomi** duplikált kérés elutasítása (nem
  csak egy felületes, két lépéses ellenőrzés), teszt
  `Queue::fake()`/`Bus::fake()`-kel és egy külön teszttel, amely
  ellenőrzi, hogy két egymás után hívott `markAsSyncing()` közül csak
  az első ad vissza `true`-t. A job-szintű `WithoutOverlapping`
  middleware és a retry/timeout finomhangolás inkább jegyzet marad (ezt
  a hívásban is megbeszéljük).

---

## 6. Backend route / controller struktúra

- `routes/web.php`-ba egy `sync-targets` resource-szerű csoport,
  `auth`+`verified` middleware alatt:
    - `GET /sync-targets` → lista (Inertia page, a bejelentkezett user
      által hozzáadott targetek + stat badge-ek)
    - `POST /sync-targets` → target hozzáadása a bejelentkezett userhez
      (validáció: `name` kötelező, `type` `in:user,organization`; ha a
      target már létezik globálisan, csak a pivot sor jön létre, nem új
      `sync_targets` sor – l. 2. pont `findOrCreateAndAttachToUser`)
    - `POST /sync-targets/{syncTarget}/sync` → `SyncRepositoriesJob`
      dispatch-elése (+ korai elutasítás, ha már `syncing`)
    - `GET /sync-targets/{syncTarget}` → a target repói (kereséssel,
      szűréssel, rendezéssel, paginálva) – az adat ugyanaz minden usernek,
      aki követi a targetet.
- **Jogosultság – egységesen Policy-val:**
  `App\Policies\SyncTargetPolicy` felel minden hozzáférési döntésért,
  nem az adatelérési réteg. A policy `view(User $user, SyncTarget
$target)` metódusa azt ellenőrzi, hogy a usernek van-e pivot sora
  (`sync_target_user`) az adott targetre. A policy a beépített Laravel
  `AuthServiceProvider`-ben regisztrálva van a `SyncTarget` modellhez,
  így a controllerekben `$this->authorize('view', $syncTarget)`
  (vagy route model binding esetén automatikus authorization) hívható.
  Ha a felhasználónak nincs jogosultsága, Laravel 403-at ad vissza –
  ez a döntés **nem** a repository rétegben történik (l. 2. pont
  `find()` metódusa), hanem kizárólag a controller/policy szintjén,
  hogy az adatelérési réteg valóban csak adatot kezeljen.
- Controllerek – a 2. pontban leírt interfészeket kapják konstruktor
  injectionnel, sosem az Eloquent modelleket közvetlenül; a
  jogosultságot mindig a fenti `SyncTargetPolicy`-n keresztül
  ellenőrzik, mielőtt a repository metódusait hívnák:
    - `App\Http\Controllers\SyncTargetController` (`index`, `store`,
      `show`) – `SyncTargetRepositoryInterface::allForUser()` /
      `::find()` / `::findOrCreateAndAttachToUser()` hívásokkal
      dolgozik (a `show`/`sync` útvonalakon előbb `authorize('view', ...)`
      fut a betöltött target modellen), és a `show`-ban a
      `GithubRepositoryRepositoryInterface::paginateForTarget()`-et hívja
      a kereséshez/szűréshez/rendezéshez/lapozáshoz.
    - `App\Http\Controllers\SyncTargetSyncController` (`store` – a sync
      indítása, hogy a "szinkronizálás indítása" ne keveredjen a CRUD
      controllerbe; itt is `authorize('view', $syncTarget)` fut a job
      dispatch előtt)
- `App\Http\Requests\StoreSyncTargetRequest` – validáció; az "egyediség"
  itt azt jelenti, hogy **a bejelentkezett user** még nem adta hozzá ezt
  a `(name, type)` targetet (a pivot táblán nézve), nem azt, hogy a
  target globálisan ne létezzen – az létezhet, csak akkor hozzácsatoljuk
  a meglévőhöz.

---

## 7. Inertia + Vue felület

- `resources/js/pages/SyncTargets/Index.vue`
    - Targetek listája kártya/táblázat formában: név, típus, státusz badge,
      utolsó sikeres szinkron ideje, utolsó hiba (ha van, röviden
      kiírva/tooltipben).
    - "Új target hozzáadása" form: `name` input + `type` explicit választó
      (radio vagy select: "Felhasználó" / "Organizáció") – `useForm` +
      Wayfinder-generált route hívás. Nincs automatikus detektálás, a
      felhasználó dönti el, melyik GitHub API végpontot hívjuk.
    - "Szinkronizálás indítása" gomb minden targetnél (disabled, ha már
      `syncing`), toast visszajelzéssel (`vue-sonner`, ami már telepítve
      van).
    - Kattintásra a target soron → navigáció a repó listához.
- `resources/js/pages/SyncTargets/Show.vue`
    - Adott target repóinak táblázata: name, description, language, stars,
      open issues, GitHub last updated.
    - Lapozás: backend oldali Laravel `paginate()` + Inertia pagination
      komponens (query stringben `?page=`), nem a teljes lista egy
      lapon – ez a query-szintű gondolkodást is jól mutatja.
    - Legalább két keresési/szűrési/rendezési lehetőség, pl.:
        1. Szabad szöveges keresés név/leírás alapján (`?search=`)
        2. Rendezés (`?sort=stargazers_count|open_issues_count|github_updated_at`, `&direction=`)
        3. (opcionális) nyelv szerinti szűrés select-ből
    - A szűrés/rendezés backend oldali query string alapján (Inertia GET
        - `preserveState`), nem kliens oldali csak-JS szűrés, hogy nagyobb
          adatmennyiségnél is működjön.
- Navigáció: a sidebare felvesszük a "Sync targets" menüpontot a meglévő
  `AppSidebarLayout` mintájára.

---

## 8. Hibakezelés és logging

- A GitHub kliens kivételei sosem jutnak ki nyers formában a userig;
  a job/service elkapja, `Log::error()`-ral logol (target id, hívott
  endpoint, HTTP status, exception message), és egy rövid,
  felhasználóbarát szöveget ment `last_sync_error`-ba
  (pl. "GitHub nem található felhasználó/organizáció ezzel a névvel.").
- Inertia oldalon a `last_sync_error` megjelenik a target sorában/kártyáján
  (nem a globális Laravel error page-en).
- Validációs hibák a sima Inertia/Fortify form error flow-n mennek
  (már van rá minta a `settings` oldalakon).
- **Cache invalidáció:** a baseline-ban nincs cache a target/repó
  listázásnál (a `CACHE_STORE=database` csak a Laravel beépített
  mechanizmusaihoz – pl. session – van jelen), így a follow-up hívásban
  erről elmondjuk, hogy nincs mit invalidálni, de ha a listázás
  cache-elve lenne (pl. gyakori repó-listák), a `upsertMany()` utáni
  `Cache::forget()`/tag-alapú invalidáció lenne a belépési pont – ezt
  jegyzet szinten a NOTES-ba is felvesszük.

---

## 9. Tesztelés terve (Pest)

**Factory-k** (a tesztek előfeltétele): `SyncTargetFactory`,
`GithubRepositoryFactory` – utóbbi a GitHub JSON mezőknek megfelelő
alapértelmezett adatokkal (`github_id`, `stargazers_count`, stb.), hogy a
tesztekben ne kelljen minden mezőt kézzel felsorolni.

**Teljesen megírt tesztek:**

1. `SyncTargetTest` – sync target hozzáadása (validáció + sikeres mentés
    - a saját targetjei közti egyediség a pivot táblán).
2. `RepositorySynchronizerTest` (vagy feature teszt a job-on) –
   `Http::fake()` egy rögzített GitHub JSON válasszal, ellenőrzi hogy a
   repók bekerülnek a DB-be a megfelelő mezőkkel, és a target státusza/
   `last_synced_at` frissül.
3. Duplikátum teszt – kétszer futtatva a synchronizer-t ugyanazzal a
   fake válasszal, a repó szám nem nő, a meglévő sor frissül (`updated_at`
   változik, de nincs új row) – ez bizonyítja az unique constraint +
   upsert logikát.
4. **Megosztott target teszt** – két külön user hozzáadja ugyanazt a
   GitHub user/org-ot; ellenőrzi, hogy csak **egy** `sync_targets` sor
   és (szinkron után) nem duplikált `repositories` sorok jönnek létre,
   de mindkét user látja a targetet a saját listájában (két pivot sor).

A repository pattern miatt a `RepositorySynchronizer` logikája a valódi
repository implementációkkal (feature/integration szint, DB-t használva)
és – ahol hasznos – mockolt repository interfészekkel (tiszta unit
szint, DB nélkül) is tesztelhető; a baseline tesztek a valódi
implementációkkal futnak, hogy az egyediség constraintet is lefedjék.

**Placeholder tesztek (névvel, `test()->todo()` vagy `markTestIncomplete`)**, a
README által is javasolt listát követve:

- GitHub API hiba (404 user not found, 403 rate limit, 5xx) kezelése
- Pagination – több oldal bejárása
- Job retry / failed job viselkedés
- Ütemezett szinkronizáció
- Hiányzó/törölt repository kezelése (reconciliation)
- Cache invalidáció (l. 8. pont)
- Érvénytelen GitHub target (pl. speciális karakterek, túl hosszú név)
- Két user egyidejű "hozzáadása" ugyanarra a targetre (race condition az
  1.4 pontban leírt `firstOrCreate` retry logikára)

---

## 10. Dokumentáció a leadáshoz

- `AI_USAGE.md` – AI eszközök, promptok, mit generált AI, mit
  változtattunk/dobtunk el, hogyan validáltuk (tesztekkel, manuális
  futtatással).
- Rövid "NOTES" szakasz (README alján vagy külön fájlban) – kész/hiányos
  részek, következő lépések, kompromisszumok.
- Ezeket a munka **végén** töltjük ki véglegesen, de érdemes a fejlesztés
  közben folyamatosan jegyzetelni, hogy ne a végén kelljen visszaemlékezni.

---

## 11. Lépésenkénti végrehajtási terv (checklist)

A fenti pontok megvalósítási sorrendje és egy "kész, ha..." kritérium
lépésenként, hogy AI-jal egyenként, ellenőrizhető adagokban lehessen
végigvinni. Minden lépés után: a hozzá tartozó teszt megírása/zöldre
futtatása, majd `pint` + `phpstan` (Larastan) futtatása, mielőtt a
következő lépésre mennénk.

1. **Migrációk + modellek + enumok** (l. 1. pont) –
   `create_sync_targets_table`, `create_sync_target_user_table`,
   `create_repositories_table`, `SyncTarget`, `GithubRepository`,
   `SyncTargetType`, `SyncStatus` enumok, `SyncTargetFactory`,
   `GithubRepositoryFactory`.
   _Kész, ha:_ `php artisan migrate:fresh` hibátlanul lefut, és a
   factory-kkal létrehozott rekordok mentése/relációi (`users()`,
   `repositories()`, `syncTarget()`) tinker/teszt szinten működnek.

2. **Adatelérési réteg** (l. 2. pont) – interfészek,
   `EloquentSyncTargetRepository`, `EloquentGithubRepositoryRepository`,
   `RepositoryServiceProvider` binding.
   _Kész, ha:_ egy gyors unit teszt mock interfésszel lefut, és a
   `markAsSyncing()` atomi update logikáját lefedő teszt zöld (két
   egymás utáni hívás közül csak az első ad `true`-t).

3. **GitHub kliens + DTO + config** (l. 3. pont) – `GitHubClient`,
   `GitHubRepositoryData` DTO, `config/services.php`, `.env.example`
   `GITHUB_TOKEN` bejegyzés, dedikált exception osztályok.
   _Kész, ha:_ `Http::fake()`-es teszt igazolja, hogy a kliens helyesen
   hívja a `/users/{user}/repos` vagy `/orgs/{org}/repos` végpontot, és
   a 404/403/5xx válaszokra a megfelelő exception dobódik.

4. **`RepositorySynchronizer`** (l. 4. pont) – DTO-k mentése
   `upsertMany()`-n keresztül, `markAsSynced`/`markAsFailed`,
   tranzakció-határ.
   _Kész, ha:_ a 9. pont 2. és 3. baseline tesztje (sikeres szinkron +
   duplikátum-mentes upsert) zöld.

5. **`SyncRepositoriesJob`** (l. 5. pont) – dispatch, `syncing` állapot
   kezelése, hiba esetén `markAsFailed`.
   _Kész, ha:_ `Queue::fake()`-es teszt igazolja, hogy a job
   dispatchelődik, és egy sikertelen GitHub hívás esetén a target
   `failed` állapotba kerül a hibaüzenettel.

6. **Route-ok, Request, Policy, Controllerek** (l. 6. pont) –
   `StoreSyncTargetRequest`, `SyncTargetPolicy`,
   `SyncTargetController`, `SyncTargetSyncController`, `routes/web.php`.
   _Kész, ha:_ `php artisan route:list` mutatja az új route-okat, és egy
   feature teszt igazolja a 403-at idegen target elérésekor, illetve a
   duplikált sync-indítás elutasítását.

7. **Vue oldalak** (l. 7. pont) – `SyncTargets/Index.vue`,
   `SyncTargets/Show.vue`, sidebar menüpont, Wayfinder route hívások.
   _Kész, ha:_ manuálisan kipróbálva (böngészőben) végigvihető a teljes
   flow: target hozzáadása → sync indítása → repólista megjelenik
   kereséssel/rendezéssel.

8. **Hibakezelés/logging finomítás** (l. 8. pont) – ha bármelyik
   korábbi lépésben csak jegyzet szinten maradt.
   _Kész, ha:_ egy szándékosan hibás GitHub hívás (pl. nem létező user)
   végigfut a UI-ig, és a felhasználó a `last_sync_error` szöveget
   látja, nem nyers exceptiont.

9. **Placeholder tesztek pontosítása** (l. 9. pont) – a README-ben
   javasolt lista alapján névvel ellátott `test()->todo()` bejegyzések.

10. **`AI_USAGE.md` + NOTES** (l. 10. pont) – a leadás előtti utolsó
    lépés, de érdemes a fenti lépések közben folyamatosan jegyzetelni.

---

**Következő lépés:** minden nyitott kérdés eldöntve, kezdjük az 1.
ponttal (migrációk, modellek).
