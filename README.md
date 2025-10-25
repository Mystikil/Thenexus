# 🌀 The Nexus — The Forgotten Server 10.98 (Extended)

[![Discord](https://img.shields.io/badge/Join-include?logo=discord&logoColor=%237B68EE&label=Discord&color=%237B68EE)](https://discord.gg/GgvreyFvdV)
[![Build VCPKG](https://img.shields.io/badge/Build-include?logo=Drone&logoColor=%23DAA520&label=VCPKG&color=%23DAA520)](https://github.com/CodingALS/forgottenserver-10.98/actions/workflows/build-vcpkg.yml)
[![Docker Build](https://img.shields.io/badge/Generate-include?logo=Docker&logoColor=%236495ED&label=Docker&labelColor=grey&color=%236495ED)](https://github.com/CodingALS/forgottenserver-10.98/actions/workflows/docker.yml)
[![Repo Size](https://img.shields.io/badge/40%20MiB-include?label=Repo%20Size&color=%23FF1493)](https://github.com/CodingALS/forgottenserver-10.98)
[![License](https://img.shields.io/badge/GPL%202.0-include?label=License&color=%23FF7F50)](https://github.com/CodingALS/forgottenserver-10.98/blob/main/LICENSE)

---

## 📜 Overview

**The Forgotten Server 10.98** is a free and open-source MMORPG server emulator written in **C++**, extended here under **The Nexus Project** with new experimental features including **engine-level instancing** and deeper Lua integration.

This fork preserves compatibility with **Tibia 10.98 protocol** (from TFS 1.4.2) while incorporating ongoing performance, structure, and feature updates from the **TFS-main** development branch.

---

## 🚀 Getting Started

### 📦 Requirements

- **C++17 or later**
- **CMake ≥ 3.16**
- **Boost Libraries (v1.78 or later)**
- **Vcpkg** for dependency management
- **MySQL Server / MariaDB**
- **LuaJIT 2.x**

### 🔧 Build Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/YourRepo/TheNexus.git
   cd TheNexus
Build with CMake

bash
Copy code
cmake -S . -B build
cmake --build build --config Release
Disable experimental features (optional)

bash
Copy code
cmake -DENABLE_INSTANCING=0 .
Run

bash
Copy code
./build/theforgottenserver
🧩 Experimental Instance System
This build introduces engine-level instancing — a framework that allows multiple isolated copies of the world to run simultaneously (for parties, dungeons, or private raids).

🔬 Core Components
File	Description
src/game/InstanceManager.{h,cpp}	Singleton managing active instances and entity registration
Creature::setInstanceId()	Moves entities between instance layers
Player::resetToWorldInstance()	Returns player to the default world
Map::getSpectatorsByInstance()	Filters spectators and events by instanceId
ProtocolGame::sendAddCreature()	Ignores cross-instance entities

Status: Early scaffolding — current focus is structure, logging, and orchestration stubs.
Scaling, party validation, and lifecycle management are in progress.

🔮 Lua Integration
A minimal Lua bridge provides high-level access to the instance system.
These examples are found under:

bash
Copy code
data/actions/scripts/instance_portal.lua
data/talkactions/scripts/instance_admin.lua
🧠 Available Functions
lua
Copy code
createInstance(cfg)
bindPlayer(uid, player)
bindParty(uid, leader)
teleportInto(uid, player)
closeInstance(uid)
getActiveInstances()
Use these to spawn and manage dungeon-like experiences from Lua.
Future updates will include:

Cooldowns

Matchmaking / party syncing

Dynamic scaling by level or power

Custom map templates per instance

## 🌐 Website Setup (XAMPP)

The PHP website that ships with The Nexus can be hosted locally with XAMPP in a
few minutes.

1. **Install XAMPP** with Apache and MySQL enabled, then copy the repository's
   `Site/` directory into your `htdocs` directory (for example
   `C:/xampp/htdocs/nexus`).
2. **Create the database** and import the schemas in order:
   - `NexusDB.sql` (root of the repository)
   - `sql/patch_001_website.sql`
3. **Update `Site/config.php`** with your MySQL credentials. While editing the
   file, set fresh values for `WEBHOOK_SECRET` and `BRIDGE_SECRET`; these are
   required for secure webhook ingestion and job polling.
4. **Create the first website owner account.** Generate a password hash
   (`php -r "echo password_hash('ChangeMe123', PASSWORD_DEFAULT), PHP_EOL;"`) and
   insert it manually:

   ```sql
   INSERT INTO website_users (email, pass_hash, role)
   VALUES ('owner@example.com', '$2y$...', 'owner');
   ```

   You can also adapt the snippet above into a one-off PHP script if you prefer
   not to run SQL by hand.
5. Visit `http://localhost/nexus/index.php` and log in with the owner account to
   configure the default theme, shop, and other settings. The new
   `/api/public_read.php` endpoints and `/api/jobs_pull.php` bridge will now use
   the secrets defined in `config.php`.

🌍 Connections
To connect to your server, use one of the following compatible clients:

OTClient V8

OTClient Redemption

Tibia Client 10.98

🧰 Troubleshooting
Boost not found: Ensure VCPKG_ROOT is correctly defined in your environment.

Missing LuaJIT headers: Confirm your include path includes $(VcpkgRoot)\include\luajit;

Linking errors: Re-run CMake with -A x64 and --fresh if migrating from 32-bit builds.

🤝 Contribution
Contributions are welcome!
Fork the project, create a new branch, commit your changes, and submit a pull request.

Please follow the existing code style and document new systems clearly — especially Lua hooks or engine extensions.

📄 License
This project is licensed under the GNU General Public License v2.0.
See LICENSE for full details.

🏷️ Credits
Original TFS Team: OTLand

Extended Fork Maintainers: CodingALS, Mystikil, and community contributors

Special Thanks: Devnexus Network, OpenTibia Community, and testers.

“A world within worlds — built not only to play, but to create.”
— The Nexus Project

yaml
Copy code

---

Would you like me to also generate a **folder index** (listing all major directories like `src/`,
