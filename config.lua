--[[========================================================================
Forgotten Server Configuration
This file controls server runtime behaviour. Settings are grouped by domain
to keep related options together and clarify their impact.
========================================================================]]

--[[========================================================================
Server Identity & Network
========================================================================]]
-- Public-facing server description and connectivity.
serverName = "Forgotten"
motd = "Welcome to The Forgotten Server!"
ip = "127.0.0.1"
bindOnlyGlobalAddress = false

-- Protocol ports and connection thresholds.
loginProtocolPort = 7171
gameProtocolPort = 7172
statusProtocolPort = 7171
statusTimeout = 5000
maxPlayers = 0
maxPacketsPerSecond = 25
replaceKickOnLogin = true

-- Session rules for how players connect and coexist.
onePlayerOnlinePerAccount = true
allowClones = false
allowWalkthrough = true

-- Status page metadata.
ownerName = ""
ownerEmail = ""
url = "https://otland.net/"
location = "Sweden"

--[[========================================================================
Database
========================================================================]]
mysqlHost = "127.0.0.1"
mysqlUser = "root"
mysqlPass = ""
mysqlDatabase = "forgottenserver"
mysqlPort = 3306
mysqlSock = ""

--[[========================================================================
Lifecycle & Monitoring
========================================================================]]
defaultPriority = "high"
startupDatabaseOptimization = false

-- Server save automation.
serverSaveNotifyMessage = true
serverSaveNotifyDuration = 5
serverSaveCleanMap = false
serverSaveClose = false
serverSaveShutdown = true

-- Logging and validation helpers.
showScriptsLogInConsole = false
showOnlineStatusInCharlist = false
showPlayerLogInConsole = true
warnUnsafeScripts = true
convertUnsafeScripts = true
forceMonsterTypesOnLoad = true
cleanProtectionZones = false
checkDuplicateStorageKeys = false

--[[========================================================================
Gameplay Rules - Combat & PvP
========================================================================]]
-- PvP environment and frag penalties.
worldType = "pvp"
protectionLevel = 1
killsToRedSkull = 3
killsToBlackSkull = 6
timeToDecreaseFrags = 24 * 60 * 60
whiteSkullTime = 15 * 60
pzLocked = 60000
stairJumpExhaustion = 2000

-- Combat assists.
hotkeyAimbotEnabled = true
experienceByKillingPlayers = false
expFromPlayersLevelRange = 75

--[[========================================================================
Equipment & Action Handling
========================================================================]]
allowChangeOutfit = true
removeChargesFromRunes = true
removeChargesFromPotions = true
removeWeaponAmmunition = true
removeWeaponCharges = true
timeBetweenActions = 200
timeBetweenExActions = 1000
classicEquipmentSlots = false
classicAttackSpeed = false
emoteSpells = false

--[[========================================================================
Player Interaction & Social Limits
========================================================================]]
freePremium = false
kickIdlePlayerAfterMinutes = 15
maxMessageBuffer = 4
yellMinimumLevel = 2
yellAlwaysAllowPremium = false
minimumLevelToSendPrivate = 1
premiumToSendPrivate = false
vipFreeLimit = 20
vipPremiumLimit = 100
depotFreeLimit = 2000
depotPremiumLimit = 10000

--[[========================================================================
Character Progression & Rates
========================================================================]]
deathLosePercent = -1

-- Staged experience multipliers.
experienceStages = {
	{ minlevel = 1, maxlevel = 8, multiplier = 7 },
	{ minlevel = 9, maxlevel = 20, multiplier = 6 },
	{ minlevel = 21, maxlevel = 50, multiplier = 5 },
	{ minlevel = 51, maxlevel = 100, multiplier = 4 },
	{ minlevel = 101, multiplier = 3 }
}

-- Base rates when stages do not apply.
rateExp = 5
rateSkill = 3
rateLoot = 2
rateMagic = 3
rateSpawn = 1

-- Stamina regeneration.
staminaSystem = true
timeToRegenMinuteStamina = 3 * 60
timeToRegenMinutePremiumStamina = 10 * 60

--[[========================================================================
World & Environment
========================================================================]]
mapName = "forgotten"
mapAuthor = "Komic"
defaultWorldLight = true

-- Pathfinding cadence.
pathfindingInterval = 200
pathfindingDelay = 300

-- Monster lifecycle.
deSpawnRange = 2
deSpawnRadius = 50
removeOnDespawn = true
walkToSpawnRadius = 15
monsterOverspawn = false

--[[========================================================================
Economy & Housing
========================================================================]]
housePriceEachSQM = 1000
houseRentPeriod = "never"
houseOwnedByAccount = false
houseDoorShowPrice = true
onlyInvitedCanMoveHouseItems = true

marketOfferDuration = 30 * 24 * 60 * 60
premiumToCreateMarketOffer = true
checkExpiredMarketOffersEachMinutes = 60
maxMarketOffersAtATimePerPlayer = 100
