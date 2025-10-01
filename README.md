# Exercise 1

## Description and Instructions

### Description
- Develop an application for managing itineraries for leisure travellers.
- The itinerary should be managed as a list, which can be accessed and modified.

### Instructions
- Develop a single page web application implementing the user stories (see below).
- The frontend of the application should communicate with the backend of the application over a REST interface.
- Data must be stored in a local SQL Database.
- Preferably you create a docker container for your application.
- Make sure your application has a contact section with names of the group members.

---

## User Stories

### Register User
**As a traveller I can register to the site and create a profile**

#### Acceptance Criteria
- No credential checking must be implemented (password, etc.)
- Traveller information should contain **Email address** and **Name**

---

### Create Itinerary
**As a traveller I can create an itinerary which shows transport and accommodation for a trip such that I can view it later.**

#### Acceptance Criteria
An itinerary must have at least the following fields:
- **Title**, e.g. "Family Trip to Norway"
- **Destination**
- **Start date** of the trip
- **Short description** of the trip (max. 80 chars), e.g. "Explore the fjords of southern Norway."
- **Detail description** of the trip (long text)

---

### View Itinerary
**As a traveller I can view my itinerary such that I have an overview over my travel arrangement.**

#### Acceptance Criteria
- When signed in, a list of all my itineraries is shown (**title**, **start date**)
- I can select an itinerary and all details are shown
