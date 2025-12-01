-- create.sql
-- Restaurant Database System schema (MySQL / XAMPP)

DROP DATABASE IF EXISTS RestaurantDB;
CREATE DATABASE RestaurantDB;
USE RestaurantDB;

-- Safely drop tables in dependency order (cleaning it all up)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `OrderItems`;
DROP TABLE IF EXISTS `Payments`;
DROP TABLE IF EXISTS `RecipeComponents`;
DROP TABLE IF EXISTS `Orders`;
DROP TABLE IF EXISTS `Ingredients`;
DROP TABLE IF EXISTS `MenuItems`;
DROP TABLE IF EXISTS `Employees`;
DROP TABLE IF EXISTS `Customers`;
SET FOREIGN_KEY_CHECKS = 1;

--  Strong entities

CREATE TABLE `Customers` (
  `CustomerID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name`       VARCHAR(100) NOT NULL,
  `Email`      VARCHAR(255),
  `Phone`      VARCHAR(30),
  `Address`    VARCHAR(255),
  PRIMARY KEY (`CustomerID`)
);

CREATE TABLE `Employees` (
  `EmployeeID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name`       VARCHAR(100) NOT NULL,
  `Role`       VARCHAR(50)  NOT NULL,
  `Schedule`   VARCHAR(100) NOT NULL,
  `Salary`     DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`EmployeeID`)
);

CREATE TABLE `Ingredients` (
  `IngredientID`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name`              VARCHAR(100) NOT NULL,
  `QuantityAvailable` DECIMAL(10,2) NOT NULL,
  `Unit`              VARCHAR(20)  NOT NULL,
  `ReorderLevel`      DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`IngredientID`)
);

CREATE TABLE `MenuItems` (
  `ItemID`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `Name`        VARCHAR(100) NOT NULL,
  `Description` TEXT,
  `Price`       DECIMAL(10,2) NOT NULL,
  `Category`    VARCHAR(50),
  `IsActive`    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`ItemID`)
);

--  Orders and dependent tables

CREATE TABLE `Orders` (
  `OrderID`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `CustomerID`  INT UNSIGNED NOT NULL,
  `EmployeeID`  INT UNSIGNED,
  `OrderDate`   DATE         NOT NULL,
  `Status`      VARCHAR(30)  NOT NULL,
  `Channel`     VARCHAR(30)  NOT NULL,
  `TotalAmount` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`OrderID`),
  CONSTRAINT `fk_orders_customer`
    FOREIGN KEY (`CustomerID`)
    REFERENCES `Customers`(`CustomerID`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_employee`
    FOREIGN KEY (`EmployeeID`)
    REFERENCES `Employees`(`EmployeeID`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

CREATE TABLE `OrderItems` (
  `OrderID` INT UNSIGNED NOT NULL,
  `LineNo`  INT UNSIGNED NOT NULL,
  `ItemID`  INT UNSIGNED NOT NULL,
  `Quantity` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`OrderID`, `LineNo`),
  CONSTRAINT `fk_orderitems_order`
    FOREIGN KEY (`OrderID`)
    REFERENCES `Orders`(`OrderID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_orderitems_menuitem`
    FOREIGN KEY (`ItemID`)
    REFERENCES `MenuItems`(`ItemID`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

CREATE TABLE `Payments` (
  `OrderID`   INT UNSIGNED NOT NULL,
  `PaymentNo` INT UNSIGNED NOT NULL,
  `Method`    VARCHAR(30)  NOT NULL,
  `Amount`    DECIMAL(10,2) NOT NULL,
  `Status`    VARCHAR(30),
  PRIMARY KEY (`OrderID`, `PaymentNo`),
  CONSTRAINT `fk_payments_order`
    FOREIGN KEY (`OrderID`)
    REFERENCES `Orders`(`OrderID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

CREATE TABLE `RecipeComponents` (
  `ItemID`       INT UNSIGNED NOT NULL,
  `ComponentNo`  INT UNSIGNED NOT NULL,
  `IngredientID` INT UNSIGNED NOT NULL,
  `QtyPerItem`   DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`ItemID`, `ComponentNo`),
  CONSTRAINT `fk_recipe_item`
    FOREIGN KEY (`ItemID`)
    REFERENCES `MenuItems`(`ItemID`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_recipe_ingredient`
    FOREIGN KEY (`IngredientID`)
    REFERENCES `Ingredients`(`IngredientID`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
