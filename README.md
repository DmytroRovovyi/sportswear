# Sportswear

<div style="
    padding: 20px; 
    border-radius: 8px; 
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji';
    font-size: 16px; 
    line-height: 1.5; 
    word-wrap: break-word;
">

## About the project

The project was created purely for test development of importing and filtering XML files.

<br>

## Project Initialization

Follow these steps when cloning and setting up the project for the first time:

<ol>
<li><code>git clone git@github.com:DmytroRovovyi/sportswear.git</code></li>
<li><code>cd sportswear</code></li>
<li>Get the database files for the project from the developer (<code>.env</code> and <code>file.sql</code>)</li>
<li><code>ddev start</code></li>
<li><code>ddev composer install</code></li>
<li>Move <code>file.sql</code> to the initial project directory</li>
<li>Replace the <code>.env.example</code> file with <code>.env</code></li>
<li><code>ddev import-db --src=file.sql</code></li>
<li><code>ddev artisan migrate</code> - check if there are any saved changes in the database</li>
<li>After, remove the sql file from the initial project directory (it should not be stored in the project)</li>
<li>Check that you have npm and node, then run the commands to enable the style</li>
</ol>

```bash
ddev ssh
```
```bash
npm rub dev
```

## Project REST API

The project provides two GET endpoints to retrieve product data and filter options.

### 🔹 `GET /api/catalog/products`

Returns a list of products with support for pagination, sorting, and filtering.

### 🔹 `GET /api/catalog/filters`

Returns available filter values for all products (used to populate filter UI on the frontend).

**Query Parameters:**

| Parameter               | Type     | Description                                                                 |
|-------------------------|----------|-----------------------------------------------------------------------------|
| `page`                  | integer  | Page number (e.g. `1`, `2`, etc.)                                           |
| `limit`                 | integer  | Number of products per page (e.g. `5`, `10`, etc.)                          |
| `sort_by`               | string   | Sorting criteria (e.g. `id`, `name_asc`, `price_asc`)        |
| `filter[category_id][]` | string    | Filter by category ID (e.g. `00000020941`)                                  |
| `filter[vendor][]`      | string    | Filter by vendor name (e.g. `NIKE`, `ADIDAS`, `47 Brand`)                   |
| `filter[brand][]`       | string    | Filter by brand name (e.g. `Zeus`, `AQUA SPEED`)                            |
| `filter[color][]`       | string    | Filter by color name (e.g. `чорний`, `хакі`)                                |
| `filter[appointment][]` | string    | Filter by appointment (e.g. `Баскетбол`, `Біг`)                             |
| `filter[gender][]`      | string    | Filter by gender (e.g. `Чоловіки`, `Діти`)                                  |

## Project Console Commands
```bash
php artisan import:xml All_RRC_UAH_2nd_floor.xml
```
Handles importing product data from an XML file and updates the Redis cache for filters.
```bash
php artisan cache:rebuild-filters
```
The project provides console commands for importing data and managing the Redis filter cache.

</div>
