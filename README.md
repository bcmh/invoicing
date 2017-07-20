BCMH Invoicing
===

Render invoices from Xero according to company template.

We use this app to render our Invoices in HTML/CSS then output PDFs. We've found that
Chrome's __Print > Save as PDF__ renders the best documents. We've used this approach as we wanted to use 


Getting Started
---

```
composer install
php -S localhost:8001
```

Create a `.env` using `.env-example` as a guide for the required information.

Visit the [localhost:8001](http://localhost:8001)