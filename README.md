# UniMarket-University-Marketplace-Web-Application
A Campus based marketplace among the university students

# UniMarket 

### A University-Based Online Marketplace Platform

UniMarket is a full-stack web application designed exclusively for university students to buy and sell products within their campus community. The platform provides a secure, organized, and user-friendly alternative to informal trading on social media by offering categorized listings, admin-controlled product approval, and cart-based purchasing.

---

## 🚀 Features

* User registration and login with secure authentication
* University email–based access control
* Product submission with image upload (admin approval required)
* Admin dashboard for approving or deleting products
* Categorized product browsing
* Cart system with real-time quantity updates using AJAX
* User profile management with profile picture upload
* Responsive and clean UI design
* Secure database interaction using prepared statements

---

## 🛠️ Technologies Used

### Backend

* **PHP 8+**
* **MySQL**

### Frontend

* **HTML5**
* **CSS3**
* **JavaScript**
* **AJAX**

### Tools & Environment

* Apache Server (XAMPP)
* phpMyAdmin
* Visual Studio Code
* Git & GitHub

---

## 🗄️ Database Structure

* **users** (user_id, name, email, password, is_admin, profile_picture)
* **products** (product_id, user_id, name, price, status, image, approved)
* **cart** (cart_id, user_id, product_id, quantity)

The database follows normalization principles to ensure data integrity and efficient querying.

---

## ⚙️ Installation & Setup

1. Clone the repository

   ```bash
   git clone https://github.com/R3dra09/UniMarket-University-Marketplace-Web-Application
   ```

2. Move the project to the XAMPP `htdocs` directory

3. Start **Apache** and **MySQL** from XAMPP

4. Import the database

   * Open phpMyAdmin
   * Create a database named `unimarket`
   * Import the provided SQL file

5. Configure database connection in PHP files

6. Open the project in browser

   ```
   http://localhost/unimarket
   ```

---

## 🔐 Security Features

* Password hashing using `password_hash()`
* Session-based authentication
* Prepared statements to prevent SQL injection
* Input validation and file upload size/type checking

---

## 🧪 Testing

* Unit testing of individual modules
* Integration testing for database and backend logic
* System testing for complete user workflows
* Manual testing using different user roles

---

## 📌 Future Improvements

* Online payment integration
* Real-time buyer–seller chat
* Advanced search and filtering
* Recommendation system
* Mobile app version

---

## 👨‍💻 Developer

**Md. Sabbir Hosen**
Department of Electrical and Computer Engineering
Rajshahi University of Engineering & Technology (RUET)

---

## 📄 License

This project is developed for academic purposes.
Feel free to fork and modify for learning or educational use.

---

