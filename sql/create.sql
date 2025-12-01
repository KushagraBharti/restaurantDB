CREATE DATABASE IF NOT EXISTS RestaurantDB;
USE RestaurantDB;

DROP TABLE IF EXISTS `OrderItems`;
DROP TABLE IF EXISTS `Payments`;
DROP TABLE IF EXISTS `RecipeComponents`;
DROP TABLE IF EXISTS `Orders`;
DROP TABLE IF EXISTS `Ingredients`;
DROP TABLE IF EXISTS `MenuItems`;
DROP TABLE IF EXISTS `Employees`;
DROP TABLE IF EXISTS `Customer`;

CREATE TABLE `Customer` (
  `CustomerID` INTEGER NOT NULL,
  `Name` TEXT NOT NULL,
  `Email` TEXT,
  `PhoneNo` TEXT,
  `Address` TEXT,
  PRIMARY KEY (`CustomerID`)
);

CREATE TABLE `Employees` (
  `EmployeeID` INTEGER NOT NULL,
  `Name` TEXT NOT NULL,
  `Role` TEXT NOT NULL,
  `Schedule` TEXT NOT NULL,
  `Salary` REAL NOT NULL,
  PRIMARY KEY (`EmployeeID`)
);

CREATE TABLE `Ingredients` (
  `IngredientID` INTEGER NOT NULL,
  `Name` TEXT NOT NULL,
  `QuantityAvailable` INTEGER NOT NULL,
  `ReorderLevel` REAL NOT NULL,
  `Unit` TEXT NOT NULL,
  PRIMARY KEY (`IngredientID`)
);

CREATE TABLE `MenuItems` (
  `ItemID` INTEGER NOT NULL,
  `Name` TEXT NOT NULL,
  `Description` TEXT,
  `Price` REAL NOT NULL,
  `Category` TEXT,
  `IsActive` INTEGER DEFAULT 1,
  PRIMARY KEY (`ItemID`)
);

CREATE TABLE `Orders` (
  `OrderID` INTEGER NOT NULL,
  `CustomerID` INTEGER NOT NULL,
  `EmployeeID` INTEGER,
  `OrderDate` TEXT NOT NULL,
  `Status` TEXT NOT NULL,
  `Channel` TEXT NOT NULL,
  `TotalAmount` REAL NOT NULL,
  PRIMARY KEY (`OrderID`),
  FOREIGN KEY (`CustomerID`) REFERENCES `Customer`(`CustomerID`),
  FOREIGN KEY (`EmployeeID`) REFERENCES `Employees`(`EmployeeID`)
);

CREATE TABLE `OrderItems` (
  `OrderID` INTEGER NOT NULL,
  `LineNo` INTEGER NOT NULL,
  `ItemID` INTEGER NOT NULL,
  `Quantity` INTEGER NOT NULL,
  PRIMARY KEY (`OrderID`, `LineNo`),
  FOREIGN KEY (`ItemID`) REFERENCES `MenuItems`(`ItemID`),
  FOREIGN KEY (`OrderID`) REFERENCES `Orders`(`OrderID`)
);

CREATE TABLE `Payments` (
  `OrderID` INTEGER NOT NULL,
  `PaymentNo` INTEGER NOT NULL,
  `Method` TEXT NOT NULL,
  `Amount` REAL NOT NULL,
  `Status` TEXT,
  PRIMARY KEY (`OrderID`, `PaymentNo`),
  FOREIGN KEY (`OrderID`) REFERENCES `Orders`(`OrderID`)
);

CREATE TABLE `RecipeComponents` (
  `ItemID` INTEGER NOT NULL,
  `ComponentNo` INTEGER NOT NULL,
  `IngredientID` INTEGER NOT NULL,
  `QtyperItem` REAL NOT NULL,
  PRIMARY KEY (`ItemID`, `ComponentNo`),
  FOREIGN KEY (`IngredientID`) REFERENCES `Ingredients`(`IngredientID`),
  FOREIGN KEY (`ItemID`) REFERENCES `MenuItems`(`ItemID`)
);
