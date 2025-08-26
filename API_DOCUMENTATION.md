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

## 8. Pet Memorial & Donation Endpoints

These endpoints manage the pet memorial and donation system.

### 8.1 Mark a Pet as Deceased

Permanently marks a pet as deceased. This action is irreversible and can only be performed by the pet's owner.

- **Endpoint**: `POST /api/mark-pet-deceased.php`
- **Parameters** (form-data):
  - `pet_id` (integer, required): The ID of the pet to mark as deceased.
  - `csrf_token` (string, required): The CSRF token for security.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Pet has been marked as deceased."
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "You do not own this pet."
  }
  ```

### 8.2 Configure a Memorial

Allows the pet owner to enable or disable the memorial page and set a donation goal.

- **Endpoint**: `POST /api/configure-memorial.php`
- **Parameters** (form-data):
  - `pet_id` (integer, required): The ID of the deceased pet.
  - `enable_memorial` (boolean, optional): `1` to enable, `0` to disable the memorial.
  - `donation_goal` (number, optional): A donation goal between 0 and 1000.
  - `csrf_token` (string, required): The CSRF token for security.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Memorial settings updated successfully."
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "Invalid donation goal. Must be between $0 and $1000."
  }
  ```

### 8.3 Make a Donation

Allows a logged-in user to make a donation to a pet's memorial fund.

- **Endpoint**: `POST /api/make-donation.php`
- **Parameters** (form-data):
  - `pet_id` (integer, required): The ID of the pet receiving the donation.
  - `amount` (number, required): The amount to donate in USD.
  - `message` (string, optional): A supportive message to leave for the owner.
  - `csrf_token` (string, required): The CSRF token for security.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Donation successful. Thank you for your contribution."
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "Donation amount exceeds the remaining goal."
  }
  ```

---

## 9. Pet Memorial & Donation Endpoints (NEW)

These endpoints manage the pet memorial and donation system for deceased pets.

### 9.1 Mark Pet as Deceased

Permanently marks a pet as deceased and converts their page to a memorial. This action is irreversible and can only be performed by the pet's owner.

- **Endpoint**: `POST /api/mark-pet-deceased.php`
- **Request Body** (form-data):
  - `pet_id` (integer, required): The ID of the pet to mark as deceased
  - `csrf_token` (string, required): CSRF protection token
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Pet has been moved to a memorial."
  }
  ```
- **Error Responses**:
  ```json
  {
    "success": false,
    "message": "You do not own this pet."
  }
  ```

### 9.2 Configure Memorial Settings

Allows the pet owner to enable/disable the memorial page and set donation goals.

- **Endpoint**: `POST /api/configure-memorial.php`
- **Request Body** (form-data):
  - `pet_id` (integer, required): The ID of the deceased pet
  - `enable_memorial` (boolean, optional): Enable public memorial and donations
  - `donation_goal` (number, optional): Donation goal ($0-$1000 USD)
  - `csrf_token` (string, required): CSRF protection token
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Memorial settings updated successfully."
  }
  ```
- **Error Response**:
  ```json
  {
    "success": false,
    "message": "Invalid donation goal. Must be between $0 and $1000."
  }
  ```

### 9.3 Make Memorial Donation

Allows logged-in users to make donations to pet memorial funds.

- **Endpoint**: `POST /api/make-donation.php`
- **Request Body** (form-data):
  - `pet_id` (integer, required): The ID of the pet receiving the donation
  - `amount` (number, required): Donation amount in USD (must be positive)
  - `message` (string, optional): Optional supportive message for the owner
  - `csrf_token` (string, required): CSRF protection token
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Thank you for your generous donation!"
  }
  ```
- **Error Responses**:
  ```json
  {
    "success": false,
    "message": "This pet is not accepting donations at this time."
  }
  ```
  ```json
  {
    "success": false,
    "message": "The donation goal for this memorial has been met."
  }
  ```

---

## 10. Pet Mating Request Endpoints (NEW)

These endpoints manage the mating request system between compatible pets.

### 10.1 Send Mating Request

Allows a user to send a mating request from their pet to another user's compatible pet.

- **Endpoint**: `POST /api/send-mating-request.php`
- **Request Body** (form-data):
  - `requester_pet_id` (integer, required): ID of the requesting pet (must be owned by user)
  - `requested_pet_id` (integer, required): ID of the target pet for mating
  - `csrf_token` (string, required): CSRF protection token
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Mating request sent successfully!"
  }
  ```
- **Error Responses**:
  ```json
  {
    "success": false,
    "message": "Pets must be of opposite genders to mate."
  }
  ```
  ```json
  {
    "success": false,
    "message": "Both pets must be at least 18 pet days old."
  }
  ```
  ```json
  {
    "success": false,
    "message": "One or both pets are on a breeding cooldown."
  }
  ```

### 10.2 Respond to Mating Request

Allows a user to accept or reject a mating request for their pet. Accepting triggers the breeding process.

- **Endpoint**: `POST /api/respond-to-mating-request.php`
- **Request Body** (form-data):
  - `request_id` (integer, required): ID of the mating request
  - `action` (string, required): Either "accept" or "reject"
  - `csrf_token` (string, required): CSRF protection token
- **Success Response (200 OK)**:
  For rejection:
  ```json
  {
    "success": true,
    "message": "Mating request rejected."
  }
  ```
  For acceptance:
  ```json
  {
    "success": true,
    "message": "Mating request accepted! A new pet has been born!"
  }
  ```
- **Error Responses**:
  ```json
  {
    "success": false,
    "message": "You are not authorized to respond to this request."
  }
  ```
  ```json
  {
    "success": false,
    "message": "This mating request has already been responded to."
  }
  ```
  ```json
  {
    "success": false,
    "message": "One or both pets are not old enough to breed."
  }
  ```

---

## 11. Enhanced Pet Detail Endpoints

### 11.1 Get Pet Details with Memorial Information

Retrieves comprehensive pet information including memorial status and donation data.

- **Endpoint**: `GET /pet.php?id={pet_id}`
- **Parameters**:
  - `id` (integer, required): The pet ID
- **Response**: HTML page with pet details including:
  - Basic pet information (name, age, gender, description)
  - Owner information
  - Memorial status and donation progress (if applicable)
  - Mating request interface (for compatible pets)
  - Recent donations list (for memorial pets)

### Key Features in Pet Detail Page:
- **Memorial Management**: Pet owners can mark pets as deceased and configure memorial settings
- **Donation System**: Community members can make donations with optional messages
- **Mating Requests**: Users can send mating requests for compatible pets of opposite genders
- **Progress Tracking**: Visual donation goal progress with percentage completion

---

## 12. System Validations & Business Logic

### Pet Memorial System
- Only pet owners can mark their pets as deceased
- Memorial settings can only be configured for deceased pets
- Users cannot donate to their own pet memorials
- Donation amounts are automatically capped at remaining goal amount
- Memorial pages become public upon enabling donations

### Mating Request System
- Pets must be of opposite genders to mate
- Both pets must be at least 18 pet days old
- Pets on breeding cooldowns cannot participate
- Only one pending request allowed between the same pair of pets
- Successful mating creates offspring with genetic inheritance
- Both parent pets receive happiness boosts after successful mating
- 24-hour breeding cooldown applied to both parents after mating

### Security Features
- All endpoints require CSRF token validation
- Proper ownership verification for all pet actions
- Session-based authentication required
- Input validation and sanitization on all user data
- Transaction rollback on any breeding errors

---

*Documentation updated for Money Paws v3.0.0+*
