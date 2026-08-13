# Kodutöö: spotihindade rakendus

**Positsioon:** PHP tarkvaraarendaja, Qilowatt
**Eeldatav ajakulu:** 4–6 tundi
**Tähtaeg:** 7 kalendripäeva ülesande saamisest

---

## 1. Taust

Qilowatt juhib akusid, invertereid ja muid seadmeid Nord Pooli päev-ette (day-ahead) börsihindade alusel. Praktiliselt iga meie funktsionaalsus algab samast kohast: võta tunnihinnad, tee nendest arusaadav pilt ja tee nende põhjal otsus.

Selles ülesandes ehitad selle ahela väikeses mahus läbi. Meid huvitab, kuidas sa koodi struktureerid, kuidas käitud reaalse API veidrustega ja kuidas esitad tulemuse kasutajale — mitte see, kas jõuad maksimaalse arvu funktsioonideni.

---

## 2. Ülesanne

Ehita väike PHP veebirakendus, mis pärib Eesti (EE) hinnapiirkonna börsihinnad, arvutab neist mõned näitajad, visualiseerib need ja saadab tulemuse meile.

### 2.1 Andmed

Kasuta Eleringi avalikku API-t (autentimist ei vaja):

```
GET https://dashboard.elering.ee/api/nps/price?start=2026-08-09T21:00:00.000Z&end=2026-08-10T20:59:59.999Z
GET https://dashboard.elering.ee/api/nps/price/EE/current
```

Vastus on JSON kujul `{"success": true, "data": {"ee": [{"timestamp": ..., "price": ...}, ...], "fi": [...], ...}}`. Hinnad on **EUR/MWh käibemaksuta**, ajatemplid **UTC Unix-sekundid**. Olemas on ka CSV-variant (`/api/nps/price/csv?...&fields=ee`).

Kasutaja peab saama valida kuupäeva. Vaikimisi näita tänast päeva.

### 2.2 Arvutused

Rakendus arvutab valitud päeva kohta vähemalt:

| Näitaja | Selgitus |
|---|---|
| Miinimum, maksimum, keskmine | valitud päeva lõikes |
| Hind snt/kWh | teisendatud EUR/MWh-st, käibemaksuga ja käibemaksuta |
| Odavaim järjestikune aken | N järjestikust perioodi madalaima keskmise hinnaga, kus N on kasutaja valitav (1–6 h) |
| Kalleim järjestikune aken | sama loogika teises suunas |

Käibemaksumäär, võrgutasu ja müüja marginaal loe konfiguratsioonist, ära kirjuta neid koodi sisse. Eesti käibemaks on hetkel 24%.

### 2.3 Kasutajaliides

Üks leht, mis on loetav ka telefonis. Ilus disain pole hindamiskriteerium, arusaadavus on.

- Tabel kõigi perioodide hindadega
- Graafik (tulpdiagramm või joondiagramm) — Chart.js CDN-ist, puhas SVG või mis iganes sulle sobib, build-samm ei ole vajalik
- Värvieristus: keskmisest odavamad ja kallimad perioodid selgelt eristatavad, odavaim aken esile tõstetud
- Kuupäevavalik ja akna pikkuse valik
- Selge veateade, kui andmeid pole (nt homseid hindu pole enne kella 14:00 avaldatud)

### 2.4 Püant: esitamine käib rakenduse enda kaudu

Rakenduses on nupp **"Saada tulemus"**, mis avab vormi väljadega **nimi, e-post, telefon**. Vormi saatmisel valideerib rakendus sisendi serveri poolel ja saadab e-kirja aadressile **jobs@qilowatt.eu**.

Kiri peab sisaldama:

- kandidaadi nimi, e-post, telefon
- link GitHubi repole ja viimase commiti SHA
- valitud kuupäev ja hinnapiirkond
- arvutatud näitajad: keskmine, miinimum, maksimum, odavaim aken (algusaeg ja keskmine hind)
- saatmise ajatempel Europe/Tallinn ajavööndis ja kasutatud PHP versioon

**Kodutöö loetakse esitatuks siis, kui see kiri meieni jõuab.** Meili saatmise seadista keskkonnamuutujatega (nt oma SMTP, Gmaili app password, Mailtrap vms) — ära pane paroole repositooriumisse. `.env.example` peab olema olemas.

Isikuandmetega käitu nagu päris tootes: valideeri, ära logi neid faili ega konsooli, ära saada kolmandatesse teenustesse peale meiliteenuse.

---

## 3. Tehnilised nõuded

- **PHP 8.2 või uuem.** Kasuta tüüpe, `declare(strict_types=1)`, PSR-12 stiili.
- **Raamistik on vabatahtlik.** Puhas PHP, Slim, Laravel, Symfony — kõik sobivad. Ära vali raamistikku ainult mulje pärast; oskame lugeda ka 300 rida hästi struktureeritud vanilla PHP-d.
- **Composer** on lubatud ja soovitatud.
- **Vahemälu on kohustuslik.** Eleringi API-t ei tohi pärida iga lehelaadimisel. Failipõhine cache on täiesti piisav; TTL konfigureeritav.
- **Ühiktestid.** Vähemalt hinnaarvutuse ja libisevate akende loogika peavad olema PHPUnitiga kaetud. Testid peavad jooksma ilma võrguühenduseta — see tähendab, et API-klient ja arvutusloogika peavad olema lahutatud.
- **Käivitamine** peab töötama ühe käsuga: kas `php -S localhost:8000 -t public` või `docker compose up`. Kirjelda README-s.
- Andmebaasi ei ole vaja.

### Kohad, kus tähelepanu tasub

Need pole lõksud, vaid päris asjad, millega me iga päev tegeleme:

1. **Ajavööndid.** API annab UTC, kasutaja mõtleb Europe/Tallinn ajas. Eesti ööpäev algab suveajal 21:00 UTC ja talveajal 22:00 UTC.
2. **Kellakeeramise päevad.** Ööpäevas võib olla 23 või 25 tundi. Kood, mis eeldab kõvasti 24 perioodi, läheb märtsis ja oktoobris katki.
3. **Periood ei pruugi olla tund.** Alates 2025. aasta oktoobrist on Euroopa päev-ette turul kasutusel 15-minutiline arveldusperiood, seega API võib tagastada nii 60- kui 15-minutilise lahutuse. Kirjuta kood nii, et see ei sõltuks perioodi pikkusest.
4. **Negatiivsed hinnad on normaalsed.** Ära filtreeri neid välja ja ära lase graafikul katki minna.
5. **API võib olla maas.** Aegumine, uuesti proovimine või graatsiline vearežiim — vali ise, aga tee teadlik valik.

---

## 4. Boonusülesanded

Vali **kuni kaks**, kui aega ja tahtmist on. Kaks korralikult tehtud boonust on parem kui viis poolikut.

- **Akusimulatsioon.** 10 kWh / 5 kW aku laeb odavaimatel perioodidel ja tühjeneb kallimatel etteantud tarbimisprofiili juures. Näita päevast kokkuhoidu võrreldes olukorraga, kus akut poleks. Märgi tulemus selgelt hinnanguks — tegelik tulemus sõltub tarbimisest, tariifidest ja hindadest.
- **Mitu piirkonda.** Võrdle EE, FI, LV ja LT hindu samal graafikul.
- **Ajalugu.** Näita valitud päeva hindu viimase 30 päeva keskmise taustal.
- **API endpoint.** Anna samad andmed välja JSON-ina, koos lihtsa dokumentatsiooniga.
- **CI.** GitHub Actions, mis jooksutab testid ja staatilise analüüsi (PHPStan vms).

---

## 5. Hindamiskriteeriumid

| Mida vaatame | Kaal |
|---|---|
| Koodi struktuur ja loetavus, vastutuste jaotus | 30% |
| Arvutuste korrektsus, sh ajavööndid ja äärejuhud | 25% |
| Töötav lahendus, mille saab käivitada README järgi | 20% |
| Testid ja vigade käsitlus | 15% |
| Kasutajaliidese arusaadavus | 10% |

Boonusülesanded ei anna lisapunkte, kui põhiosa on poolik.

---

## 6. Esitamine

1. Avalik GitHubi (või GitLabi) repo koos README-ga: kuidas käivitada, millised valikud tegid ja miks, mis jäi tegemata ja mida teeksid järgmisena.
2. `.env.example` koos kõigi vajalike muutujatega.
3. Vajuta oma rakenduses nuppu "Saada tulemus" ja saada tulemus aadressile **jobs@qilowatt.eu**.

Kui meili saatmine kohalikult kuidagi tööle ei lähe, kirjuta sellest README-s ja saada kiri käsitsi — aga kirjelda, mida proovisid. Töötav lahendus on parem kui vaikimine.

---

## 7. AI-tööriistade kasutamine

Copilot, Claude, Cursor ja muu sarnane on lubatud — kasutame neid isegi. Ainus tingimus: pead oskama iga rea kohta selgitada, miks see seal on ja mis juhtub, kui seda muuta. Järgmises vestlusvoorus käime koodi koos läbi ja palume midagi väikest juurde teha.

---

## 8. Mida me ei oota

- Kasutajate registreerimist, sisselogimist ega rollide haldust
- Andmebaasi ega migratsioone
- Pikslitäpset disaini või disainisüsteemi
- 100% testikatvust
- Et sa töötaksid rohkem kui kirjas olev ajakulu. Kui aeg saab otsa, kirjuta README-sse, mis jäi pooleli — see on täiesti korrektne vastus.

Küsimused on teretulnud: **jobs@qilowatt.eu**.
