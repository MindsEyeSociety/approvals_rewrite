# MES Approvals System — Administrator Guide

This guide covers the day-to-day management tasks available to storytellers and administrators of the MES Approvals portal. It is written for people with storyteller or national-level access, not developers.

---

## Table of Contents

1. [Roles and Hierarchy](#1-roles-and-hierarchy)
2. [Creating a New Organisation (Domain, Region, etc.)](#2-creating-a-new-organisation)
3. [Creating a New VSS](#3-creating-a-new-vss)
4. [Assigning Storyteller Positions](#4-assigning-storyteller-positions)
   - [Assigning a VST](#assigning-a-vst)
   - [Assigning a DST or aDST](#assigning-a-dst-or-adst)
   - [Assigning an RST](#assigning-an-rst)
   - [Assigning an NST or aNST](#assigning-an-nst-or-anst)
   - [Removing a Position](#removing-a-position)
5. [Assistants vs Full Storytellers](#5-assistants-vs-full-storytellers)
6. [Application Approval Flow](#6-application-approval-flow)
7. [User Profiles and Email Addresses](#7-user-profiles-and-email-addresses)
8. [Character Management](#8-character-management)
9. [Super User Capabilities](#9-super-user-capabilities)
10. [Published Mechanics](#10-published-mechanics)

---

## 1. Roles and Hierarchy

The system organises clubs into a four-level hierarchy:

```
Globe
  └─ Nation
       └─ Region
            └─ Domain
```

> **Note:** A fifth level, Chapter, exists in the database from an earlier era but is retired. Leave the Chapter field blank when creating or editing organisations.

Each level of the hierarchy maps to a storyteller title:

| Org Level | Full Storyteller | Assistant |
|-----------|-----------------|-----------|
| Domain    | DST             | aDST      |
| Region    | RST             | *(see below)* |
| Nation    | NST             | *(see below)* |
| Globe     | Globe Admin     | —         |
| VSS       | VST             | —         |

**Two types of storyteller positions exist:**

- **VST (Venue Storyteller):** Assigned to a specific VSS (one person per VSS). Manages characters and applications within that single VSS. Assigned by editing the VSS itself.

- **Org-level STs (DST, RST, NST, Globe):** Assigned to an organisation. Automatically have authority over *all* VSSs that belong to their organisation and every organisation beneath them in the hierarchy.

---

## 2. Creating a New Organisation

**Who can do this:** Super users, or any storyteller at or above the level of the org being created. An RST can create a Domain within their Region; a DST cannot create a Region.

**Navigation:** Storyteller menu → Org List → fill in the creation form at the bottom of the page.

**Fields:**

| Field | Description |
|-------|-------------|
| Globe | Always "Camarilla" — do not change |
| Nation | Country or continental grouping (e.g. "USA") |
| Region | Regional subdivision (e.g. "New England") |
| Domain | The domain being created (e.g. "Massachusetts") |
| Chapter | **Retired — leave blank** |
| Org Name | The display name shown throughout the system |
| City / State / Country | Physical location |
| Administrator | The user who will be the org's admin contact |
| Contact Email | The organisation's public contact email |

Fill in the hierarchy fields only down to the level of the new org. For example, when creating a Domain, fill in Globe, Nation, Region, and Domain — leave Chapter blank.

The system will reject the submission if an organisation already exists with the same Nation/Region/Domain combination.

Once created, the org immediately appears in all dropdown lists throughout the system.

---

## 3. Creating a New VSS

A VSS (Venue Style Sheet) is the game-specific ruleset for a particular venue (e.g. Vampire, Werewolf) within an organisation. Each VSS has exactly one VST.

**Who can do this:** Any storyteller who has authority over the target organisation.

**Navigation:** Player menu → Venue Style Sheet List → fill in the creation form.

**Fields:**

| Field | Description |
|-------|-------------|
| Name | The VSS name, e.g. "Domain of Boston — Vampire" |
| Organisation | The org this VSS belongs to (must already exist) |
| Venue | The game line (Vampire, Werewolf, etc.) |
| Storyteller | The VST who will run this VSS |
| Contact Email | Email address for this VSS |
| VSS Document | The full text of the venue style sheet |

Once created, the VSS appears in character and application dropdowns immediately.

---

## 4. Assigning Storyteller Positions

### Assigning a VST

VSTs are assigned directly on the VSS, not through the User Display page.

1. Storyteller menu → Venue Style Sheet List.
2. Find the VSS and click **Edit**.
3. Change the **Storyteller** dropdown to the new user.
4. Save.

The previous VST loses access to that VSS as soon as the change is saved.

---

### Assigning a DST or aDST

1. Storyteller menu → User List — find the user.
2. Click their name to open their profile, then click **Edit**.
3. Scroll to the **Storyteller Positions** section.
4. In the **Add Position** form:
   - Select the Domain organisation from the dropdown.
   - Leave the Venue dropdown blank (leave it unconstrained) unless this is a venue-specific position.
   - Tick **Assistant** to assign an aDST instead of a full DST.
5. Submit. The new position appears in the table immediately.

---

### Assigning an RST

Follow the same steps as for a DST, but select the Region-level organisation from the dropdown. Leave **Assistant** unticked — the system ignores the assistant flag at Region level and treats all RSTs as having full authority.

---

### Assigning an NST or aNST

Follow the same steps, selecting the Nation-level organisation. Tick **Assistant** for an aNST.

> **Important:** Despite the assistant flag being settable for Nation-level positions, the system currently ignores it — NSTs and aNSTs receive identical permissions. The distinction is retained for organisational record-keeping only.

---

### Removing a Position

1. Open the user's profile in edit mode.
2. In the **Storyteller Positions** table, click the **Delete** link next to the position to remove.

The user loses access immediately.

---

## 5. Assistants vs Full Storytellers

The assistant flag on a storyteller position controls two things.

### Final authority

A **full storyteller** (assistant = off) has *final authority* within their organisation. When they approve an application, it is complete at their level — no further signature is required from above (unless the application requires a higher approval level).

An **assistant** (assistant = on) does not have final authority. Their approval advances the application up the chain rather than completing it at their level.

### Approval tier access

| Level | Full storyteller can set status to | Assistant can set status to |
|-------|-----------------------------------|----------------------------|
| Domain (DST) | Pending High, Pending Mid, Pending Low | Pending Mid, Pending Low |
| Region (RST) | Pending Top and below | *(same as full — flag ignored)* |
| Nation (NST) | Approved and below | *(same as full — flag ignored)* |
| Globe | Approved (all levels) | — |

A storyteller at any level can Deny an application outright, regardless of assistant status.

**No storyteller can approve their own character's applications.** The system enforces this automatically.

---

## 6. Application Approval Flow

Applications move through a chain of statuses based on the approval level they require:

```
Pending Low → Pending Mid → Pending High → Pending Top → Pending Global → Approved
```

The **Required Approval** field on each application sets how far up the chain it must travel. An application requiring "Low" approval only needs a DST signature; one requiring "Top" needs an NST.

When a storyteller approves an application:
- If the application's required approval level matches or is below the storyteller's level, it advances to the next status (or goes straight to Approved if no higher level is needed).
- If the required level is above the storyteller's level, the application advances one step but remains Pending.

**Statuses explained:**

| Status | Meaning |
|--------|---------|
| Pending Low | Waiting for DST or above |
| Pending Mid | Waiting for DST (full) or above |
| Pending High | Waiting for RST or above |
| Pending Top | Waiting for NST or above |
| Pending Global | Waiting for Globe Admin or super user |
| Approved | Fully approved |
| Denied | Rejected — requires a comment explaining why |
| Removed | Withdrawn or cancelled |

---

## 7. User Profiles and Email Addresses

**Profile data is read-only in this portal.** A member's name, email address, WW membership number, and username are pulled from the MES Portal (an external system) and cannot be edited here. If a member needs their email address updated, they must do so through the MES Portal directly. The change will appear in the approvals portal on their next login.

**What can be edited in the approvals portal:**

- **Organisation contact email** — on the Org edit page (Storyteller menu → Org List → Edit).
- **VSS contact email** — on the VSS edit page (Venue Style Sheet List → Edit).
- **Storyteller positions** — add or remove roles via the user's edit page.

---

## 8. Character Management

### Viewing a character

Any storyteller who has authority over the character's VSS or org can view the character sheet and background via Storyteller menu → VSS/Org Character List, or by following a link from an application.

### Reassigning a character to a different VSS

1. Open the character's display page.
2. Click **Reassign** next to the VSS name.
3. Select the new VSS.

The character's acceptance status resets to Pending. The new VST must accept the character into their VSS before it is fully rostered.

### Org-level characters

Characters can be registered directly to an organisation rather than a specific VSS. These characters appear in the org's character lists rather than any VSS roster and are managed by the org's storytellers.

### Marking a character dead or retired

In the character edit form, tick the **Dead/Retired** checkbox. This:
- Sets the character as inactive.
- Automatically moves all open applications for that character to **Removed** status.

This action cannot be undone through the web interface — contact a super user if a character needs to be reinstated.

---

## 9. Super User Capabilities

Super users have unrestricted access to every feature in the system:

- Create, edit, and delete organisations at any level.
- Create, edit, and delete venues and application categories.
- Edit and delete any user account.
- Approve applications at any level, including Global.
- View and manage all VSSs regardless of org affiliation.
- Publish custom mechanics.

**Super user status cannot be granted through the web interface.** It must be set directly in the `users` database table by a system administrator. Contact the technical administrator to grant or revoke super user access.

---

## 10. Published Mechanics

Published Mechanics are game rules that have been formally approved through the approvals process and made available for all players to reference.

**Publishing mechanics:** Requires Nation-level access or above (or super user). Navigate to Player menu → Published Mechanics, select the relevant approved application, and publish.

**Viewing mechanics:** Available to all logged-in users via Player menu → Published Mechanics.

---

## 11. Server Configuration (for technical administrators)

### Credentials file

Database passwords and OAuth secrets are stored in `include/settings.inc` on the server. This file is **gitignored and never committed**. If you need to deploy a fresh server or rotate credentials:

1. Fill in `include/settings.inc.example` with real values and save it as `include/settings.inc`.
2. Copy it to the server:
   ```bash
   scp include/settings.inc user@server:/var/www/approvals_rewrite/include/settings.inc
   ```

Never paste credentials into any other PHP file or commit them to git.

### Deploying updates

```bash
cd /var/www/approvals_rewrite
git pull
```

The deploy user must have an SSH key registered with GitHub. See `README.md` for full setup instructions.
