# The test task "Discounts"

The repo implements the test task "Discounts" by the Teamleader published at
https://github.com/teamleadercrm/coding-test

## Installation

To install the application is enough to clone the repository to the project
directory, install composer dependencies, and configure `.env` file

```bash
cd /var/www/project
git clone https://github.com/vdLubart/discounts.git
composer install
mv .env.example .env
```

Edit `.env` file and edit environment variables.

To run application locally start two local servers and specify the second one in
the `.env` file. The second server simulate external server to get data about
customers and products.

```bash
php -S localhost:8080 -t public
php -S localhost:8081 -t public
```

## Usage

To use application send the `curl` request:

```bash
curl --location --request POST 'http://localhost:8080/discount/gold-customer' \
--form 'id="1"' \
--form 'customer-id="2"' \
--form 'items[0][product-id]="B102"' \
--form 'items[0][quantity]="10"' \
--form 'items[0][unit-price]="4.99"' \
--form 'items[0][total]="49.90"' \
--form 'total="49.90"'
```

You will get the following response:

```json
{
    "totalDiscount": 4.99,
    "id": "1",
    "customer-id": "2",
    "items": [
        {
            "product-id": "B102",
            "quantity": "10",
            "unit-price": "4.99",
            "total": "49.90"
        }
    ],
    "total": 44.91
}
```

## API Endpoints

Every endpoint expect order data in the request form-data

- POST /discount/gold-customer
- POST /discount/sixth-switcher-for-free
- POST /discount/cheapest-tool

## Tests

To test the code run the following command:

```bash

phpunit

```

Tests coverage is 99.22% the reports can be found at `tests/coverage`