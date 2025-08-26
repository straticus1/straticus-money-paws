# Money Paws API Documentation

Welcome to the Money Paws API documentation. This guide provides the information you need to interact with the platform's API endpoints.

## 1. Authentication

All API requests must be authenticated. Our API uses a session-based authentication system. Users must be logged in to the platform to access any of the endpoints.

- **Mechanism**: Session Cookie
- **Requirement**: The user must have a valid session created by logging into the platform via the web interface. The session cookie will be sent automatically with requests made from the same browser.

---

## 2. Pet Adventures Endpoints

These endpoints manage the Pet Adventures feature.

### 2.1 Get Available Quests

Fetches a list of adventure quests available for a specific pet, based on its level.

- **Endpoint**: `GET /api/get-adventure-quests.php`
- **Parameters**:
  - `pet_id` (integer, required): The ID of the pet.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "quests": [
      {
        "id": 1,
        "name": "A Walk in the Park",
        "description": "A simple stroll to stretch the legs and sniff around.",
        "min_level": 1,
        "duration_minutes": 10,
        "energy_cost": 5,
        "experience_reward": 20
      }
    ]
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "Error message describing the issue."
  }
  ```

### 2.2 Start an Adventure

Starts a new adventure for a specified pet.

- **Endpoint**: `POST /api/start-adventure.php`
- **Request Body** (JSON):
  ```json
  {
    "pet_id": 123,
    "quest_id": 1
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Your pet has started the adventure!",
    "end_time": "YYYY-MM-DD HH:MM:SS"
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "This pet is already on an adventure."
  }
  ```

### 2.3 Check Adventure Status

Checks for any completed adventures for the logged-in user, finalizes them, and returns a report of the rewards.

- **Endpoint**: `GET /api/check-adventures.php`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "report": {
      "completed_count": 1,
      "total_exp": 50,
      "level_ups": 1,
      "items": [101, 205]
    }
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "An error occurred while checking adventures."
  }
  ```

---

## 3. Pet Breeding & Genetics Endpoints

### 3.1 Breed Pets

Creates a new offspring pet from two parent pets owned by the user. This process involves genetic inheritance and applies a breeding cooldown to both parents.

- **Endpoint**: `POST /api/breed-pets.php`
- **Parameters** (form-data):
  - `mother_id` (integer, required): The ID of the female parent pet.
  - `father_id` (integer, required): The ID of the male parent pet.
  - `name` (string, optional): The name for the new offspring pet.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Congratulations! You have a new pet!",
    "new_pet_id": 456
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "The mother is still on a breeding cooldown."
  }
  ```

---

## 4. Marketplace Endpoints

These endpoints manage the player-driven marketplace.

### 4.1 List an Item

Lists a specified quantity of an item from the user's inventory on the marketplace.

- **Endpoint**: `POST /api/list-item.php`
- **Request Body** (JSON):
  ```json
  {
    "item_id": 101,
    "quantity": 10,
    "price": 5.50
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Item listed successfully."
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "You do not have enough of this item to sell."
  }
  ```

### 4.2 List a Pet

Lists a pet owned by the user on the marketplace.

- **Endpoint**: `POST /api/list-pet.php`
- **Request Body** (JSON):
  ```json
  {
    "pet_id": 123,
    "price": 150.00
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Pet listed successfully."
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "This pet is currently on an adventure and cannot be sold."
  }
  ```

### 4.3 Get Marketplace Listings

Retrieves all active listings from the marketplace.

- **Endpoint**: `GET /api/get-marketplace-listings.php`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "listings": [
      {
        "id": 1,
        "listing_type": "pet",
        "price": "150.00",
        "seller_name": "PlayerOne",
        "pet_name": "Fido",
        "pet_description": "A very good boy."
      },
      {
        "id": 2,
        "listing_type": "item",
        "price": "5.50",
        "quantity": 10,
        "seller_name": "PlayerTwo",
        "item_name": "Bacon Treats",
        "item_description": "Crispy bacon strips that pets love."
      }
    ]
  }
  ```

### 4.4 Purchase a Listing

Purchases an item or pet from the marketplace.

- **Endpoint**: `POST /api/purchase-listing.php`
- **Request Body** (JSON):
  ```json
  {
    "listing_id": 1
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Purchase successful!"
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "You do not have enough funds to make this purchase."
  }
  ```

---

---

## 5. Pet Care Endpoints

Endpoints for interacting with and caring for pets.

### 5.1 Feed a Pet

Reduces a pet's hunger level.

- **Endpoint**: `POST /api/feed-pet.php`
- **Request Body** (JSON):
  ```json
  {
    "pet_id": 123,
    "item_id": 101
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Fido was fed successfully!",
    "new_hunger_level": 85
  }
  ```

### 5.2 Give a Treat to a Pet

Increases a pet's happiness level.

- **Endpoint**: `POST /api/treat-pet.php`
- **Request Body** (JSON):
  ```json
  {
    "pet_id": 123,
    "item_id": 201
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Fido loved the treat!",
    "new_happiness_level": 95
  }
  ```

---

## 6. Store Endpoints

Endpoints for interacting with the item store.

### 6.1 Get Store Items

Retrieves a list of all items available for purchase in the store.

- **Endpoint**: `GET /api/get-store-items.php`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "items": [
      {
        "id": 101,
        "name": "Kibble",
        "description": "Basic pet food.",
        "price": 5.00,
        "category": "food"
      }
    ]
  }
  ```

### 6.2 Purchase an Item

Purchases an item from the store and adds it to the user's inventory.

- **Endpoint**: `POST /api/purchase-item.php`
- **Request Body** (JSON):
  ```json
  {
    "item_id": 101,
    "quantity": 2
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Purchase successful!"
  }
  ```

---

## 7. User Data Endpoints

Endpoints for retrieving user-specific data.

### 7.1 Get User's Pets

Retrieves a list of all pets owned by the currently logged-in user.

- **Endpoint**: `GET /api/get-user-pets.php`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "pets": [
      {
        "id": 123,
        "name": "Fido",
        "species": "Dog",
        "level": 5
      }
    ]
  }
  ```

### 7.2 Get User Balance

Retrieves the current cryptocurrency balance for the logged-in user.

- **Endpoint**: `GET /api/get-user-balance.php`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "balance": {
      "BTC": "0.00500000",
      "ETH": "0.10000000",
      "USDC": "50.00"
    }
  }
  ```

---

*More endpoints will be documented here as they are developed.*
