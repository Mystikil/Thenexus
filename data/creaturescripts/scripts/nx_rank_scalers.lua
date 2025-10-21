-- nx_rank_scalers.lua
-- Handles runtime stat adjustments derived from monster ranks. Responsible
-- for scaling incoming/outgoing damage, loot, experience and cleaning up
-- visual state on death.

local function getTierFromCreature(creature)
    if not creature then
        return nil
    end
    return NX_RANK.getRankForCreature(creature)
end

local function clampMitigation(value)
    if value < 0 then
        return 0
    end
    if value > 0.80 then
        return 0.80
    end
    return value
end

local function adjustOutgoing(attacker, primary, secondary)
    local tier = getTierFromCreature(attacker)
    if not tier then
        return primary, secondary
    end
    local mult = tier.dmg or 1
    if mult == 1 then
        return primary, secondary
    end
    if primary > 0 then
        primary = math.max(0, math.floor(primary * mult))
    end
    if secondary > 0 then
        secondary = math.max(0, math.floor(secondary * mult))
    end
    return primary, secondary
end

local function adjustIncoming(target, primary, secondary)
    local tier = getTierFromCreature(target)
    if not tier then
        return primary, secondary
    end
    local mit = clampMitigation(tier.mit or 0)
    if mit > 0 then
        primary = math.max(0, math.floor(primary * (1 - mit)))
        secondary = math.max(0, math.floor(secondary * (1 - mit)))
    end
    local resist = 0
    if tier.resist then
        if type(tier.resist) == "number" then
            resist = tier.resist
        elseif type(tier.resist) == "table" then
            resist = tier.resist.percent or 0
        end
    end
    if resist > 0 then
        primary = math.max(0, math.floor(primary * (1 - resist)))
        secondary = math.max(0, math.floor(secondary * (1 - resist)))
    end
    return primary, secondary
end

local function cleanupConditions(monster)
    if monster:getStorageValue(NX_RANK.STORAGE.haste) == 1 then
        monster:removeCondition(CONDITION_HASTE, CONDITIONID_COMBAT, NX_RANK.STORAGE.haste)
        monster:setStorageValue(NX_RANK.STORAGE.haste, -1)
    end
end

local function applyExtraXp(monster, killer, tier)
    if not killer or not killer:isPlayer() then
        return
    end
    local mType = monster:getType() or MonsterType(monster:getName())
    if not mType then
        return
    end
    local baseExp = mType:getExperience() or 0
    local desired = baseExp * (tier.xp or 1)
    local extra = math.floor(desired - baseExp)
    if extra > 0 then
        killer:addExperience(extra, true)
    end
end

local function scaleLoot(monster, tier)
    -- Proper loot scaling requires integration with the central loot
    -- handling pipeline. This placeholder exists to make the call-site
    -- explicit without risking runtime errors.
    return tier and tier.loot_mult
end

function onHealthChange(monster, attacker, primaryDamage, primaryType, secondaryDamage, secondaryType, origin)
    if attacker and attacker:isMonster() then
        primaryDamage, secondaryDamage = adjustOutgoing(attacker, primaryDamage, secondaryDamage)
    end
    if monster and monster:isMonster() then
        primaryDamage, secondaryDamage = adjustIncoming(monster, primaryDamage, secondaryDamage)
    end
    return primaryDamage, primaryType, secondaryDamage, secondaryType
end

function onDeath(monster, corpse, killer, mostDamageKiller, unjustified, mostDamageUnjustified)
    if not monster or not monster:isMonster() then
        return true
    end
    local tier = getTierFromCreature(monster)
    if tier then
        applyExtraXp(monster, killer, tier)
        scaleLoot(monster, tier)
    end
    cleanupConditions(monster)
    NX_RANK.RUNTIME[monster:getId()] = nil
    return true
end
