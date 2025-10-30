# Baby Proofers - Gravity Form + WooCommerce Integration

## Overview

This project aims to create an interactive, multi-page **Gravity Form** that allows users to select baby proofing products for each room in their home. The form dynamically loads products from WooCommerce using **Gravity Perks - Populate Anything**, and allows users to either download their selections as a PDF or proceed to checkout for purchase.

---

## 🧩 Core Goals

* Create a **multi-step form** that guides users through each room in their home.
* Dynamically populate available **products (WooCommerce)** into the form via **Gravity Perks - Populate Anything**.
* Enable **conditional logic** based on user selections (e.g., number of bedrooms).
* Allow users to **download selections (PDF)** or **proceed to checkout** directly.
* Ensure each room only shows products relevant to that room, based on a predefined spreadsheet.

---

## 🧰 Plugins Used

The following plugins are installed and required for this setup:

1. **Gravity Forms Elite**
2. **Gravity Flow**
3. **Gravity Flow PDF**
4. **Gravity Perks - Populate Anything**
5. **Gravity Perks - Nested Forms**
6. **WooCommerce**

---

## ⚙️ Implementation Plan

### Step 1: Add All Products to WooCommerce

* Import all baby proofing products into WooCommerce.
* Ensure each product has:

  * Title, price, image, and SKU.
  * Room mapping data (as per spreadsheet).
  * Visibility set to **public**.

### Step 2: Prepare Product-Room Mapping

* Use the provided spreadsheet to define which products belong to which rooms.
* Example:

  | Product Name      | Available In Rooms   |
  | ----------------- | -------------------- |
  | Cabinet Lock      | Kitchen, Bathroom    |
  | Corner Guard      | Living Room, Bedroom |
  | Outlet Plug Cover | All Rooms            |

### Step 3: Build the Master Gravity Form

* Create a **Master Form** that includes all room forms using **Nested Forms**.
* Each **Room Form** acts as a sub-form with image choices tied to WooCommerce products.

### Step 4: Populate WooCommerce Products into Form Fields

* Use **Gravity Perks - Populate Anything** to dynamically load WooCommerce products.
* Each image choice field displays:

  * Product image
  * Product title
  * Product price

### Step 5: Conditional Logic

* Add logic to show rooms based on user input.
* Example:

  * If user selects **2 bedrooms**, only show Bedroom 1 and Bedroom 2 forms.
  * Use **conditional page logic** to manage room visibility.

### Step 6: Multi-Page Form Structure

Each page represents a separate room.

**Form Layout Example:**

1. Welcome Page → Select number of bedrooms.
2. Living Room
3. Kitchen
4. Bathroom
5. Bedroom 1
6. Bedroom 2 (Conditional)
7. Bedroom 3 (Conditional)
8. Summary / Checkout Page

### Step 7: Checkout and PDF Options

* After the form is completed, provide options:

  * **Download Selections as PDF** (via Gravity Flow PDF)
  * **Proceed to Checkout** (auto-add selected items to WooCommerce cart)
  * **Do Both**

### Step 8: Testing & Validation

* Test form logic and WooCommerce product mapping.
* Verify that only selected products are passed to the checkout.
* Ensure PDF summary correctly lists selected products.

---

## 🖼️ System Flow Diagram

![Form Flow Diagram](https://example.com/path-to-your-diagram.png)

**Flow Explanation:**

1. User selects house details (number of rooms).
2. Rooms displayed conditionally.
3. Each room page shows image-based product choices.
4. User reviews selections.
5. User downloads PDF and/or proceeds to WooCommerce checkout.

---

## ✅ Deliverables

* Fully functional multi-step Gravity Form integrated with WooCommerce.
* Conditional logic setup for dynamic room display.
* Product-to-room mapping via spreadsheet.
* Checkout and PDF output functionality.

---

## 📂 File Replacement

To update the project image or diagram, replace the following URL in the README:

```
https://example.com/path-to-your-diagram.png
```

with the actual image URL once uploaded.

---

## 🧠 Notes

* Gravity Perks license must be active for Populate Anything and Nested Forms features.
* Gravity Flow PDF plugin handles all downloadable PDF logic.
* WooCommerce cart actions may require custom hooks for product addition from form entries.

---

## 🧾 Summary

This setup combines Gravity Forms' flexibility with WooCommerce's eCommerce power to create a user-friendly baby proofing configurator. The final result allows users to customize and purchase safety products based on their home layout, with dynamic logic, visual choices, and checkout integration.
