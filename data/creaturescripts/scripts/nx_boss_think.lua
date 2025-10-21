-- nx_boss_think.lua
-- Very light-weight placeholder for the boss phase system. It wires the
-- hooks and demonstrates how rank derived scalars could be consumed. The
-- full combat behaviour should be implemented gradually.

local bossState = {}

local function getBossEntry(monster)
    if not monster or not monster:isMonster() then
        return nil
    end
    return NX_BOSS.getEntry(monster:getName())
end

local function getState(monster)
    local cid = monster:getId()
    bossState[cid] = bossState[cid] or { triggered = {} }
    return bossState[cid]
end

local function resetState(monster)
    bossState[monster:getId()] = nil
end

local function readRankScalars(monster)
    local ai = monster:getStorageValue(NX_RANK.STORAGE.ai_cd_mult)
    if type(ai) ~= "number" or ai <= 0 then
        ai = 1
    end
    local unlock = monster:getStorageValue(NX_RANK.STORAGE.spell_unlock)
    if type(unlock) ~= "number" or unlock < 0 then
        unlock = 0
    end
    local resist = monster:getStorageValue(NX_RANK.STORAGE.resist)
    if type(resist) ~= "number" or resist < 0 then
        resist = 0
    end
    return ai, unlock, resist
end

local function handlePhase(monster, phaseIdx, entry)
    local state = getState(monster)
    if state.triggered[phaseIdx] then
        return
    end
    state.triggered[phaseIdx] = true
    local phase = entry.phases[phaseIdx]
    if not phase then
        return
    end
    local name = monster:getName()
    Game.broadcastMessage(string.format("%s grows more dangerous!", name), MESSAGE_STATUS_WARNING)
end

local function evaluatePhases(monster, entry)
    if not entry or not entry.phases then
        return
    end
    local healthPercent = monster:getHealth() / monster:getMaxHealth() * 100
    for idx, phase in ipairs(entry.phases) do
        if healthPercent <= phase.threshold then
            handlePhase(monster, idx, entry)
        end
    end
end

function onSpawn(monster)
    resetState(monster)
    local state = getState(monster)
    state.ai_cd_mult, state.spell_unlock, state.rank_resist = readRankScalars(monster)
    return true
end

function onThink(monster)
    local entry = getBossEntry(monster)
    if not entry then
        return true
    end
    evaluatePhases(monster, entry)
    return true
end

function onHealthChange(monster, attacker, primaryDamage, primaryType, secondaryDamage, secondaryType, origin)
    local entry = getBossEntry(monster)
    if entry then
        evaluatePhases(monster, entry)
    end
    return primaryDamage, primaryType, secondaryDamage, secondaryType
end

function onTarget(monster, target)
    return true
end

function onDeath(monster, corpse, killer, mostDamageKiller, unjustified, mostDamageUnjustified)
    resetState(monster)
    return true
end
