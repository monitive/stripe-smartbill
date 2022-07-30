# Stripe To Smartbill

🇬🇧 Generate [Smartbill](https://www.smartbill.ro) invoices based on [Stripe](https://stripe.com) payments &amp; invoices. This tool is useful for Romanian organizations that use Smartbill for invoicing while using Stripe as their payment processor. Therefore, documentation and instructions below are in Romanian language.

🇷🇴 Acest script vanilla PHP cu dependinte minime genereaza facturi Smartbill pe baza platilor si facturilor Stripe deja emise.

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

## Teste

Acest script are teste, pentru a le executa:

```shell
$ composer test
```

## Colaborare

Pentru a semnala probleme, deschide un Issue nou.

Pentru a aduce imbunatatiri sau reparatii acestui script, oricing poate face fork si deschide un Pull Request inapoi.

## Licenta

Vezi [LICENSE](LICENSE).
