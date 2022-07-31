# Stripe To Smartbill

🇬🇧 Generate [Smartbill](https://www.smartbill.ro) invoices based on [Stripe](https://stripe.com) payments &amp; invoices. This tool is useful for Romanian organizations that use Smartbill for invoicing while using Stripe as their payment processor. Therefore, documentation and instructions below are in Romanian language.

🇷🇴 Acest script vanilla PHP cu dependinte minime genereaza facturi Smartbill pe baza platilor si facturilor Stripe deja emise.

## Beneficii

Acest script incarca platile din Stripe incepand cu data trimisa ca parametru, si pentru fiecare plata, genereaza facturi in Smartbill pe baza datelor din factura Stripe asociata platii.

Beneficii:

- Evita anularea facturilor neincasate prin urmarirea platilor Stripe in locul facturilor Stripe;
- Adauga informatie meta la platile pentru care se emit facturi pentru a nu emite duplicate;
- Aplica TVA corespunzator tarii in care se afla clientul facturat;
- Suporta facturi Stripe cu taxare inversa si facturi cu servicii neimpozitabile (din afara UE);
- Trece numarul facturii Stripe pe factura Smartbill in cadrul numarului de aviz;
- Verifica valoarea totala a facturilor calculata de Smartbill versus plata efectuata in Stripe;

Pe partea tehnica:

- Code standard PSR-12;
- Nu foloseste librarii externe in afara de Guzzle pentru apelurile API;
- Unit tests atat pentru Stripe cat si pentru Smartbill.

## Cerinte

- PHP 7.x sau mai mare

## Instalare

Cloneaza repository-ul:

```shell
$ git clone https://github.com/monitive/stripe-smartbill.git
```

Instaleaza dependintele composer:

```shell
$ cd stripe-smartbill
$ composer install
```

Configureaza scriptul:

```shell
$ cp ./.env.example ./.env
```

Editeaza fisierul `.env` si seteaza directivele in mod corespunzator.

Configureaza cotele de TVA:

```shell
$ cp ./vat_rates.json.example ./vat_rates.json
```

Editeaza fisierul si seteaza Cotele de TVA conform cotelor definite in [Smartbill Cloud -> Configurare -> Cote TVA](https://cloud.smartbill.ro/core/configurare/cote-tva/).

## Rulare

Pentru a genera facturi Smartbill, trimite ca si parametru data incepand cu care vrei sa generezi:

```shell
$ composer generate 2022-07-01 [--verbose]
```

Parametrii:

- `--verbose`: Afiseaza stack trace in caz de eroare.

## Limitari

In prezent exista urmatoarele limitari:

1. Se obtin maxim 100 Stripe charges la o executie. Daca este nevoie de mai multe, variantele ar fi
  a. actualizarea numarului maxim de charges obtinute de la Stripe (vezi `Stripe::MAX_ITEMS_PER_REQUEST`),
  b. rularea pe intervale mai scurte sau
  c. actualizarea scriptului sa pagineze rezultatele.
2. Scriptul (inca) nu proceseaza stornarile in Stripe.

## Teste

Acest script are teste, pentru a le executa:

```shell
$ composer test
```

Verificarea sintaxei PSR-12 se face cu:

```shell
$ composer syntax
```

Repararea problemelor minore de sintaxa se face cu:

```shell
$ composer fix
```

## Colaborare

Pentru a semnala probleme, deschide un Issue nou.

Pentru a aduce imbunatatiri sau reparatii acestui script, oricing poate face fork si deschide un Pull Request inapoi.

## Licenta

Vezi [LICENSE](LICENSE).
