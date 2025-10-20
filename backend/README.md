# 🌍 Travel Planner – aplikacija za planiranje putovanja

Aplikacija omogućava korisniku da jednostavno isplanira putovanje unosom početne lokacije, destinacije, datuma putovanja, broja putnika, budžeta, preferencija, vrste prevoza i smeštaja.  
Na osnovu unetih podataka sistem automatski generiše plan putovanja koji uključuje listu aktivnosti, prevoz, smeštaj i ukupne troškove — uz kontrolu da ne prelaze zadati budžet.

---

## Opis funkcionalnosti

Korisnik može da:

-   kreira nalog i prijavi se u sistem (autentifikacija preko Laravel Sanctum),
-   unese parametre za putovanje:
    -   početnu lokaciju i destinaciju,
    -   datume putovanja (od–do),
    -   broj putnika i raspoloživi budžet,
    -   preferencije, tip prevoza i smeštaja,
-   automatski generiše plan putovanja koji uključuje:
    -   listu aktivnosti sa tačnim vremenskim rasporedom,
    -   obavezne stavke (prevoz i smeštaj),
    -   izračunavanje ukupnih troškova i poređenje sa budžetom,
    -   pregled vremenske prognoze za destinaciju i poredjenje troškova po danu
-   pregleda i ažurira postojeće planove,
-   izvozi plan u **PDF dokument**.

Admin ima mogućnost da dodaje nove aktivnosti, pregleda korisnike i podešava sistemska podešavanja.

---

## Tehnologije

**Backend:**  
-Laravel 12 (PHP 8.2)

-   MySQL baza
-   Laravel Sanctum (autentifikacija)
-   Eloquent ORM i migracije

**Frontend:**

-   React 19.1.1
-   React Router 6.30.1
-   Bootstrap 5.3.8
-   Axios 1.12.2
-   Leaflet 1.9.4
-   React-Leaflet 5.0.0
-   Recharts 3.3.0
-   React-Icons 5.5.0

---

## Ograničenja i provere u aplikaciji

Budžet mora biti pozitivan i veći od ukupnih troškova.
Plan mora sadržati barem dva prevoza i jedan smeštaj.
Aktivnosti ne smeju imati preklapanje u vremenu.
Datumi putovanja moraju biti u ispravnom redosledu.
Aktivnost mora pripadati destinaciji plana.
Promene budžeta, datuma ili broja putnika automatski ažuriraju troškove.

## Pokretanje projekta lokalno

### Backend (Laravel)

Otvori terminal i udji u backend folder
bash
cd backend

Instaliraj php zavisnosti
composer install

Napravi .env fajl i podesi konekciju ka MySQL bazi:
cp .env.example .env

U fajlu podesi sledeće:

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=travel_planning
    DB_USERNAME=root
    DB_PASSWORD=

Pokreni migracije i seedere:
php artisan migrate --seed

Pokreni server:
php artisan serve

### Frontend (React)

Otvori novi terminal i uđi u frontend folder:
cd frontend

Instaliraj React zavisnosti:
npm install

Pokreni razvojni server:
npm start
