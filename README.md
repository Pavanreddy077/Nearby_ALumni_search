# Nearby_ALumni_search
find the alumni's nearby you..

Alumni Search by Location

A scalable PHP-based application to register alumni, track their location, and search for nearby alumni based on geolocation and shared alumni networks. 

---

Features

- User registration with name, email, location, and alumni networks
- Automatic location update on login
- Manual update of name and networks
- Search nearby alumni based on shared network(s) and distance
- Uses `PHP-MySQLi-Database-Class` ORM for DB interactions

---

Tech Stack

- **Backend**: PHP (No framework)
- **ORM**: [PHP-MySQLi-Database-Class](https://github.com/ThingEngineer/PHP-MySQLi-Database-Class)
- **Database**: MySQL
- **Location API**: `navigator.geolocation` from browser

---

Database Schema

Tables auto-created by PHP if not found:

- `users(id, name, email, latitude, longitude, created_at, updated_at)`
- `networks(id, name, description)`
- `user_networks(user_id, network_id)` – Many-to-many relationship

---

Setup Instructions:

- Clone the repo:
  - in the bash:
  - =>git clone https://github.com/Pavanreddy077/Nearby_ALumni_search.git
  - =>cd alumni-search
- Configure MySQL credentials in db_config.php.
- Start a PHP server:
  - =>php -S localhost:8000
