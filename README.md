# TaskFlow

**Aplikacja do zarządzania projektami, zadaniami i pracą zespołową** zbudowana w oparciu o **PHP, Laravel, Vue.js i Inertia.js**.

> **Status: w trakcie rozwoju (Work in Progress)**  
> Projekt jest rozwijany etapami i udostępniony publicznie w celu prezentacji kodu, zastosowanych technologii i sposobu projektowania aplikacji. Nie jest jeszcze przeznaczony do wdrożeń produkcyjnych.

TaskFlow łączy organizację pracy zespołu z rejestrowaniem czasu, raportowaniem i zarządzaniem wersjami oprogramowania. Wybrane czynności, takie jak przygotowywanie raportów czy opisów zmian w wydaniach, mogą być wspierane przez modele AI.

Projekt skupia się nie tylko na interfejsie użytkownika, ale także na logice biznesowej: uprawnieniach, walidacji danych, historii zmian, kontroli dostępu i testach automatycznych.

## Spis treści

- [Funkcjonalności](#funkcjonalności)
    - [Konta i przestrzenie robocze](#konta-i-przestrzenie-robocze)
    - [Projekty i zadania](#projekty-i-zadania)
    - [Współpraca i historia aktywności](#współpraca-i-historia-aktywności)
    - [Rejestrowanie czasu pracy](#rejestrowanie-czasu-pracy)
    - [Wersje i changelogi](#wersje-i-changelogi)
    - [Raporty i udostępnianie](#raporty-i-udostępnianie)
    - [Integracja z AI](#integracja-z-ai)
- [Technologie](#technologie)
- [Organizacja projektu](#organizacja-projektu)
- [Wymagania](#wymagania)
- [Instalacja i uruchomienie](#instalacja-i-uruchomienie)
- [Konfiguracja AI](#konfiguracja-ai)
- [Testy](#testy)
- [Aktualny stan projektu](#aktualny-stan-projektu)
- [Bezpieczeństwo](#bezpieczeństwo)
- [Licencja](#licencja)

## Funkcjonalności

Poniżej opisano moduły zaimplementowane podczas rozwoju aplikacji. Poszczególne obszary mogą nadal wymagać poprawek lub dodatkowych testów.

### Konta i przestrzenie robocze

- Rejestracja i logowanie użytkowników.
- Tworzenie przestrzeni roboczych (_workspaces_) i przełączanie się między nimi.
- Zarządzanie członkami przestrzeni roboczej.
- Role oraz ograniczanie dostępu do wybranych działań i zasobów.
- Organizowanie projektów w obrębie przestrzeni roboczych.
- Wybór języka interfejsu użytkownika (obecnie English / Polski) zapisywany na koncie użytkownika.

### Projekty i zadania

- Tworzenie i aktualizowanie projektów oraz zadań.
- Opisy zadań, statusy, priorytety, osoby odpowiedzialne i terminy realizacji.
- Wyszukiwanie, filtrowanie i sortowanie listy zadań.
- Paginacja ułatwiająca pracę z większą liczbą rekordów.
- Kontrola dostępu do projektów i zadań na podstawie uprawnień użytkownika.

### Współpraca i historia aktywności

- Komentarze i dyskusje przy zadaniach.
- Edytowanie i usuwanie własnych komentarzy zgodnie z uprawnieniami.
- Historia aktywności i zmian danych zadania.

### Rejestrowanie czasu pracy

- Dodawanie, edytowanie i usuwanie wpisów czasu pracy (_worklogs_).
- Powiązanie zarejestrowanego czasu z zadaniami.
- Dzienne i tygodniowe zestawienia pracy.
- Wykorzystanie informacji o czasie pracy w raportach.

### Wersje i changelogi

- Organizowanie informacji o wydaniach oprogramowania.
- Przygotowywanie opisów zmian (_changelogs_).
- Wspomagane przez AI generowanie wersji roboczych changelogów na podstawie danych dostępnych w aplikacji.

### Raporty i udostępnianie

- Generowanie raportów dla wybranego zakresu dat i osób.
- Przygotowywanie raportów z pomocą AI.
- Edycja i zapisywanie treści raportów w Markdown.
- Obsługa wersji roboczych i opublikowanych raportów.
- Eksport raportów do PDF.
- Udostępnianie opublikowanych raportów za pomocą linków oraz możliwość wycofania dostępu.

### Integracja z AI

TaskFlow wykorzystuje opcjonalną integrację z **DeepSeek API** do wspierania tworzenia tekstów na podstawie danych zgromadzonych w aplikacji, przede wszystkim:

- opisów zmian w wydaniach;
- podsumowań i treści raportów.

Klucz API jest przechowywany w lokalnej konfiguracji środowiskowej, a nie w kodzie repozytorium. Korzystanie z funkcji AI wymaga własnego klucza i dostępu do usługi zewnętrznej.

## Technologie

| Obszar                    | Technologie i rozwiązania                                                |
| ------------------------- | ------------------------------------------------------------------------ |
| Backend                   | PHP, Laravel                                                             |
| Frontend                  | Vue.js, Inertia.js, JavaScript                                           |
| Baza danych               | Warstwa bazodanowa Laravel; domyślnie SQLite w przykładowej konfiguracji |
| Integracje AI             | DeepSeek API                                                             |
| Zarządzanie zależnościami | Composer, npm                                                            |
| Narzędzia frontendowe     | Vite                                                                     |
| Testy                     | Testy funkcjonalne Laravel / PHP                                         |
| Konfiguracja              | Zmienne środowiskowe w `.env`                                            |

## Organizacja projektu

Aplikacja wykorzystuje typową strukturę projektu Laravel. Ważne elementy kodu obejmują:

| Katalog                 | Przeznaczenie                                      |
| ----------------------- | -------------------------------------------------- |
| `app/Http/Controllers/` | Obsługa żądań i operacji aplikacji                 |
| `app/Http/Requests/`    | Walidacja danych wejściowych                       |
| `database/migrations/`  | Definicje zmian w schemacie bazy danych            |
| `resources/js/`         | Interfejs zbudowany z komponentów i widoków Vue.js |
| `routes/`               | Definicje tras aplikacji                           |
| `tests/Feature/`        | Testy scenariuszy działania aplikacji              |

Istotną częścią implementacji są walidacja danych, oddzielenie uprawnień użytkowników od funkcji biznesowych i testowanie zachowania aplikacji. Kod rozwijany jest przyrostowo, w kolejnych etapach funkcjonalnych.

## Wymagania

Do lokalnego uruchomienia potrzebne są:

- PHP w wersji zgodnej z `composer.json` projektu wraz z wymaganymi rozszerzeniami;
- Composer;
- Node.js i npm w wersjach zgodnych z `package.json`;
- SQLite (dla domyślnej konfiguracji) albo odpowiednio skonfigurowana baza danych;
- Git.

Dokładne wymagania dotyczące wersji bibliotek znajdują się w `composer.json` i `package.json`.

## Instalacja i uruchomienie

Poniżej znajduje się standardowa procedura lokalnego uruchomienia aplikacji Laravel. W zależności od środowiska i bieżących zmian w projekcie mogą być potrzebne dodatkowe kroki.

### 1. Pobierz repozytorium

```bash
git clone https://github.com/szymonpietrek-design/taskflow.git
cd taskflow
```

### 2. Zainstaluj zależności

```bash
composer install
npm install
```

### 3. Skonfiguruj środowisko

Skopiuj plik przykładowej konfiguracji i wygeneruj klucz aplikacji:

```bash
cp .env.example .env
php artisan key:generate
```

Sprawdź wartości w `.env`, w szczególności `APP_URL`, konfigurację bazy danych i opcjonalne parametry AI.

### 4. Przygotuj bazę danych

Domyślna konfiguracja przykładowa wykorzystuje SQLite. Jeśli plik bazy jeszcze nie istnieje, utwórz go (macOS/Linux):

```bash
touch database/database.sqlite
```

Następnie uruchom migracje:

```bash
php artisan migrate
```

Jeżeli korzystasz z MySQL lub innego wspieranego przez projekt silnika bazy danych, dostosuj najpierw zmienne `DB_*` w pliku `.env`.

### 5. Uruchom aplikację

W pierwszym terminalu uruchom serwer aplikacji:

```bash
php artisan serve
```

W drugim terminalu uruchom środowisko frontendowe:

```bash
npm run dev
```

Domyślny adres aplikacji przy takim uruchomieniu to:

**http://localhost:8000**

Jeśli pracujesz w środowisku kontenerowym, np. z Laravel Sail, wykonuj odpowiednie polecenia wewnątrz kontenera i korzystaj z portów skonfigurowanych w swoim środowisku.

## Konfiguracja AI

Integracja z DeepSeek jest **opcjonalna**. W pliku `.env` można ustawić:

```dotenv
AI_CHAT_PROVIDER=deepseek
DEEPSEEK_API_KEY=
DEEPSEEK_BASE_URL=https://api.deepseek.com/v1
DEEPSEEK_CHAT_MODEL=deepseek-chat
DEEPSEEK_MAX_COMPLETION_TOKENS=2000
```

Własny klucz API należy wpisać wyłącznie do lokalnego `.env` lub bezpiecznej konfiguracji środowiska. Pozostałe wartości stanowią przykładowe ustawienia usługi.

Brak klucza może uniemożliwić korzystanie z funkcji AI, ale nie jest wymagany do samej instalacji aplikacji.

## Testy

W projekcie znajdują się testy funkcjonalne sprawdzające między innymi wybrane scenariusze raportowania, walidacji i kontroli uprawnień.

Uruchomienie całego zestawu testów:

```bash
php artisan test
```

Przykłady uruchamiania testów wybranych obszarów:

```bash
php artisan test --filter=ReportEditingTest
php artisan test --filter=ReportAiGenerationTest
php artisan test --filter=WorkspaceMembersTest
```

Zakres testów jest rozbudowywany razem z aplikacją. Nie wszystkie funkcje zostały jeszcze objęte pełnymi testami regresyjnymi.

## Aktualny stan projektu

**TaskFlow to projekt w trakcie rozwoju.** Zaimplementowano główne mechanizmy pracy z przestrzeniami roboczymi, projektami, zadaniami, komentarzami, czasem pracy, raportami i wydaniami.

Wybrane scenariusze związane z raportowaniem, edycją treści, publikacją oraz uprawnieniami posiadają testy automatyczne. Nowsze obszary, w tym zarządzanie członkami przestrzeni roboczych, wymagają dalszych testów i stabilizacji.

### Najbliższe kierunki rozwoju

- Uzupełnianie testów automatycznych i regresyjnych.
- Dalsze dopracowywanie uprawnień i obsługi użytkowników.
- Usprawnianie interfejsu i doświadczenia użytkownika.
- Rozwijanie raportowania, changelogów i funkcji AI.
- Uzupełnienie dokumentacji i dodanie zrzutów ekranu.

Opis funkcjonalności przedstawia aktualny kierunek projektu; interfejs i sposób działania poszczególnych modułów mogą się zmieniać.

## Bezpieczeństwo

- Plik `.env` oraz prawdziwe hasła i klucze API **nie powinny trafiać do repozytorium**.
- `.env.example` zawiera wyłącznie przykładową konfigurację i wartości zastępcze.
- Hasła demonstracyjne należy zmienić przed utworzeniem kont testowych.
- Przed wdrożeniem aplikacji poza lokalnym środowiskiem konieczne są dodatkowe testy, przegląd konfiguracji i zabezpieczeń.

## Licencja

Na ten moment repozytorium nie deklaruje osobnej licencji. Publiczne udostępnienie kodu służy prezentacji projektu i nie oznacza automatycznego zezwolenia na jego wykorzystanie, modyfikowanie lub redystrybucję.

---

**Repozytorium:** [github.com/szymonpietrek-design/taskflow](https://github.com/szymonpietrek-design/taskflow)  
**Status:** Work in Progress
