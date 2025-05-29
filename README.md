# PolePlace Marketplace

A multi-vendor marketplace plugin for WordPress and WooCommerce with REST API support for React Native mobile app.

## Description

PolePlace Marketplace transforms your WooCommerce store into a full-featured multi-vendor marketplace where all registered users can both buy and sell products. The plugin is designed with a REST API-first approach to power a React Native mobile app.

### Key Features

- **User Roles**: All registered users can both buy and sell products
- **Product Management**: Users can create, edit, and delete their own products
- **WooCommerce Integration**: Leverages WooCommerce for product management, orders, and payments
- **Commission System**: Automatically deducts a 5% commission from each sale
- **REST API**: Comprehensive API for mobile app integration
- **Dashboard**: Users can view their sales, profits, and product listings
- **Admin Tools**: Administrators can view comprehensive marketplace statistics

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 7.4+

## Installation

1. Upload the `pole-place-marketplace` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. Configure payment gateways in WooCommerce

## Configuration

1. **Payment Gateways**: Configure payment gateways in WooCommerce (Stripe Connect or PagSeguro recommended)
2. **Commission Rate**: The default commission rate is 5% and can be modified in the plugin constants

## REST API Documentation

### Authentication

The API uses WordPress authentication. For mobile app integration, we recommend using JWT Authentication.

### User Endpoints

#### Get User Products

```
GET /wp-json/marketplace/v1/user/products
```

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "Professional Pole Dance Kit",
      "slug": "professional-pole-dance-kit",
      "permalink": "https://example.com/product/professional-pole-dance-kit/",
      "price": "1500.00",
      "regular_price": "1500.00",
      "sale_price": "",
      "status": "publish",
      "description": "Complete professional pole dance kit...",
      "categories": [
        {
          "id": 15,
          "name": "Pole Dance Kits",
          "slug": "pole-dance-kits"
        }
      ],
      "attributes": [
        {
          "name": "Pole Diameter",
          "values": ["45mm"]
        },
        {
          "name": "Material",
          "values": ["Chrome"]
        }
      ],
      "images": [
        {
          "id": 456,
          "src": "https://example.com/wp-content/uploads/2025/05/pole-kit.jpg",
          "name": "Pole Kit",
          "alt": "Professional Pole Dance Kit"
        }
      ]
    }
  ]
}
```

#### Create User Product

```
POST /wp-json/marketplace/v1/user/products
```

**Request Body Example:**
```json
{
  "name": "Professional Pole Dance Kit",
  "regular_price": "1500.00",
  "description": "Complete professional pole dance kit...",
  "short_description": "Professional grade pole dance kit with all accessories",
  "categories": [15],
  "attributes": {
    "pole_diameter": "45mm",
    "pole_material": "Chrome",
    "grip_type": "Standard",
    "pole_height": "Adjustable",
    "mounting_type": "Convertible"
  },
  "images": [456]
}
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "Professional Pole Dance Kit",
    "slug": "professional-pole-dance-kit",
    "permalink": "https://example.com/product/professional-pole-dance-kit/",
    "price": "1500.00",
    "regular_price": "1500.00",
    "sale_price": "",
    "status": "publish",
    "description": "Complete professional pole dance kit..."
  }
}
```

#### Update User Product

```
PUT /wp-json/marketplace/v1/user/products/{id}
```

**Request Body Example:**
```json
{
  "name": "Professional Pole Dance Kit - Premium",
  "regular_price": "1600.00"
}
```

#### Delete User Product

```
DELETE /wp-json/marketplace/v1/user/products/{id}
```

#### Get User Stats

```
GET /wp-json/marketplace/v1/user/stats
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_sales": 5000.00,
      "order_count": 10,
      "commission": 250.00,
      "net_earnings": 4750.00,
      "product_count": 5,
      "average_order_value": 500.00,
      "order_frequency": 2.5
    },
    "recent_orders": [
      {
        "id": 789,
        "order_number": "789",
        "date_created": "2025-05-26T15:30:00",
        "status": "completed",
        "total": "1500.00",
        "line_items": [
          {
            "id": 1,
            "product_id": 123,
            "name": "Professional Pole Dance Kit",
            "quantity": 1,
            "subtotal": "1500.00",
            "total": "1500.00"
          }
        ]
      }
    ]
  }
}
```

### Marketplace Endpoints

#### Get Products

```
GET /wp-json/marketplace/v1/products
```

Query parameters:
- `per_page`: Number of products per page (default: 10)
- `page`: Page number (default: 1)
- `category`: Category slug
- `search`: Search term
- `min_price`: Minimum price
- `max_price`: Maximum price
- `orderby`: Order by field (default: date)
- `order`: Order direction (default: desc)

#### Get Product

```
GET /wp-json/marketplace/v1/products/{id}
```

#### Create Order

```
POST /wp-json/marketplace/v1/orders
```

**Request Body Example:**
```json
{
  "products": [
    {
      "id": 123,
      "quantity": 1
    }
  ],
  "billing": {
    "first_name": "John",
    "last_name": "Doe",
    "address_1": "123 Main St",
    "city": "São Paulo",
    "state": "SP",
    "postcode": "01000-000",
    "country": "BR",
    "email": "john.doe@example.com",
    "phone": "11999999999"
  },
  "shipping": {
    "first_name": "John",
    "last_name": "Doe",
    "address_1": "123 Main St",
    "city": "São Paulo",
    "state": "SP",
    "postcode": "01000-000",
    "country": "BR"
  },
  "payment_method": "stripe"
}
```

### Admin Endpoints

#### Get Admin Stats

```
GET /wp-json/marketplace/v1/admin/stats
```

**Response Example:**
```json
{
  "success": true,
  "data": {
    "total_sales": 50000.00,
    "total_orders": 100,
    "total_commission": 2500.00,
    "active_sellers": 20,
    "total_products": 150
  }
}
```

#### Get Admin Users

```
GET /wp-json/marketplace/v1/admin/users
```

**Response Example:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "total_sales": 5000.00,
      "order_count": 10,
      "commission": 250.00,
      "net_earnings": 4750.00,
      "product_count": 5,
      "average_order_value": 500.00,
      "order_frequency": 2.5
    }
  ]
}
```

#### Get Admin User

```
GET /wp-json/marketplace/v1/admin/users/{id}
```

## OpenAPI Specification

```yaml
openapi: 3.0.0
info:
  title: PolePlace Marketplace API
  description: API for PolePlace Marketplace WordPress plugin
  version: 1.0.0
servers:
  - url: https://example.com/wp-json
    description: WordPress REST API
paths:
  /marketplace/v1/user/products:
    get:
      summary: Get user products
      description: Returns all products created by the authenticated user
      security:
        - jwt: []
      responses:
        '200':
          description: A list of user products
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Product'
    post:
      summary: Create user product
      description: Creates a new product for the authenticated user
      security:
        - jwt: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ProductInput'
      responses:
        '201':
          description: Product created successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    $ref: '#/components/schemas/Product'
  /marketplace/v1/user/products/{id}:
    put:
      summary: Update user product
      description: Updates an existing product for the authenticated user
      security:
        - jwt: []
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ProductInput'
      responses:
        '200':
          description: Product updated successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    $ref: '#/components/schemas/Product'
    delete:
      summary: Delete user product
      description: Deletes an existing product for the authenticated user
      security:
        - jwt: []
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Product deleted successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: object
                    properties:
                      id:
                        type: integer
                      message:
                        type: string
  /marketplace/v1/user/stats:
    get:
      summary: Get user stats
      description: Returns sales statistics for the authenticated user
      security:
        - jwt: []
      parameters:
        - name: period
          in: query
          schema:
            type: string
            enum: [day, week, month, year, all]
            default: month
      responses:
        '200':
          description: User statistics
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: object
                    properties:
                      stats:
                        $ref: '#/components/schemas/UserStats'
                      recent_orders:
                        type: array
                        items:
                          $ref: '#/components/schemas/Order'
  /marketplace/v1/products:
    get:
      summary: Get products
      description: Returns all products in the marketplace
      parameters:
        - name: per_page
          in: query
          schema:
            type: integer
            default: 10
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: category
          in: query
          schema:
            type: string
        - name: search
          in: query
          schema:
            type: string
        - name: min_price
          in: query
          schema:
            type: number
        - name: max_price
          in: query
          schema:
            type: number
        - name: orderby
          in: query
          schema:
            type: string
            default: date
        - name: order
          in: query
          schema:
            type: string
            enum: [asc, desc]
            default: desc
      responses:
        '200':
          description: A list of products
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: object
                    properties:
                      products:
                        type: array
                        items:
                          $ref: '#/components/schemas/Product'
                      total:
                        type: integer
                      pages:
                        type: integer
  /marketplace/v1/products/{id}:
    get:
      summary: Get product
      description: Returns a specific product
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: Product details
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    $ref: '#/components/schemas/Product'
  /marketplace/v1/orders:
    post:
      summary: Create order
      description: Creates a new order for the authenticated user
      security:
        - jwt: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/OrderInput'
      responses:
        '201':
          description: Order created successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    $ref: '#/components/schemas/Order'
  /marketplace/v1/admin/stats:
    get:
      summary: Get admin stats
      description: Returns marketplace-wide statistics (admin only)
      security:
        - jwt: []
      parameters:
        - name: period
          in: query
          schema:
            type: string
            enum: [day, week, month, year, all]
            default: month
      responses:
        '200':
          description: Marketplace statistics
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    $ref: '#/components/schemas/AdminStats'
  /marketplace/v1/admin/users:
    get:
      summary: Get admin users
      description: Returns all users with their statistics (admin only)
      security:
        - jwt: []
      parameters:
        - name: period
          in: query
          schema:
            type: string
            enum: [day, week, month, year, all]
            default: month
      responses:
        '200':
          description: Users with statistics
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/UserWithStats'
  /marketplace/v1/admin/users/{id}:
    get:
      summary: Get admin user
      description: Returns detailed statistics for a specific user (admin only)
      security:
        - jwt: []
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
        - name: period
          in: query
          schema:
            type: string
            enum: [day, week, month, year, all]
            default: month
      responses:
        '200':
          description: User details with statistics
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  data:
                    $ref: '#/components/schemas/UserWithDetails'
components:
  securitySchemes:
    jwt:
      type: http
      scheme: bearer
      bearerFormat: JWT
  schemas:
    Product:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        slug:
          type: string
        permalink:
          type: string
        date_created:
          type: string
          format: date-time
        date_modified:
          type: string
          format: date-time
        status:
          type: string
        description:
          type: string
        short_description:
          type: string
        price:
          type: string
        regular_price:
          type: string
        sale_price:
          type: string
        on_sale:
          type: boolean
        categories:
          type: array
          items:
            type: object
            properties:
              id:
                type: integer
              name:
                type: string
              slug:
                type: string
        attributes:
          type: array
          items:
            type: object
            properties:
              name:
                type: string
              values:
                type: array
                items:
                  type: string
        images:
          type: array
          items:
            type: object
            properties:
              id:
                type: integer
              src:
                type: string
              name:
                type: string
              alt:
                type: string
        seller:
          type: object
          properties:
            id:
              type: integer
            name:
              type: string
    ProductInput:
      type: object
      properties:
        name:
          type: string
        status:
          type: string
          enum: [draft, publish, pending]
        description:
          type: string
        short_description:
          type: string
        regular_price:
          type: string
        sale_price:
          type: string
        categories:
          type: array
          items:
            type: integer
        attributes:
          type: object
          additionalProperties:
            type: string
        images:
          type: array
          items:
            type: integer
    Order:
      type: object
      properties:
        id:
          type: integer
        order_number:
          type: string
        date_created:
          type: string
          format: date-time
        status:
          type: string
        total:
          type: string
        subtotal:
          type: string
        total_tax:
          type: string
        shipping_total:
          type: string
        payment_method:
          type: string
        payment_method_title:
          type: string
        line_items:
          type: array
          items:
            type: object
            properties:
              id:
                type: integer
              product_id:
                type: integer
              name:
                type: string
              quantity:
                type: integer
              subtotal:
                type: string
              total:
                type: string
              tax:
                type: string
              seller_id:
                type: integer
              product_url:
                type: string
              product_image:
                type: string
    OrderInput:
      type: object
      properties:
        products:
          type: array
          items:
            type: object
            properties:
              id:
                type: integer
              quantity:
                type: integer
        billing:
          type: object
          properties:
            first_name:
              type: string
            last_name:
              type: string
            address_1:
              type: string
            address_2:
              type: string
            city:
              type: string
            state:
              type: string
            postcode:
              type: string
            country:
              type: string
            email:
              type: string
            phone:
              type: string
        shipping:
          type: object
          properties:
            first_name:
              type: string
            last_name:
              type: string
            address_1:
              type: string
            address_2:
              type: string
            city:
              type: string
            state:
              type: string
            postcode:
              type: string
            country:
              type: string
        payment_method:
          type: string
    UserStats:
      type: object
      properties:
        total_sales:
          type: number
        order_count:
          type: integer
        commission:
          type: number
        net_earnings:
          type: number
        product_count:
          type: integer
        average_order_value:
          type: number
        order_frequency:
          type: number
    UserWithStats:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        email:
          type: string
        total_sales:
          type: number
        order_count:
          type: integer
        commission:
          type: number
        net_earnings:
          type: number
        product_count:
          type: integer
        average_order_value:
          type: number
        order_frequency:
          type: number
    UserWithDetails:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        email:
          type: string
        registered:
          type: string
          format: date-time
        stats:
          $ref: '#/components/schemas/UserStats'
        products:
          type: array
          items:
            $ref: '#/components/schemas/Product'
        orders:
          type: array
          items:
            $ref: '#/components/schemas/Order'
    AdminStats:
      type: object
      properties:
        total_sales:
          type: number
        total_orders:
          type: integer
        total_commission:
          type: number
        active_sellers:
          type: integer
        total_products:
          type: integer
```

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by PolePlace Team.
