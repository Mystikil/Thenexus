-- nx_rank_look.lua
-- Appends rank information to the look description based on reveal rules.

local function canSeeRank(player, monster)
    if not player or not monster then
        return false
    end
    if player:getGroup():getId() >= 3 then
        return true
    end
    if player:getStorageValue(NX_RANK.REVEAL.PERM) == 1 then
        return true
    end
    local tempUntil = player:getStorageValue(NX_RANK.REVEAL.TEMP_UNTIL)
    if tempUntil and tempUntil ~= -1 and tempUntil > os.time() then
        return true
    end
    if NX_RANK.REVEAL.LORE_LVL and NX_RANK.REVEAL.LORE_LVL > 0 then
        local skill = player:getSkillLevel(SKILL_MAGIC)
        if skill >= NX_RANK.REVEAL.LORE_LVL then
            return true
        end
    end
    return false
end

local function buildScalerString(tier)
    if not tier then
        return ""
    end
    return string.format(" (HPx%.2f DMGx%.2f Lootx%.2f)", tier.hp or 1, tier.dmg or 1, tier.loot_mult or 1)
end

function onLook(player, thing, position, distance)
    local description = thing:getDescription(distance)
    if not thing:isMonster() then
        return description
    end

    local rankKey = NX_RANK.getRankKey(thing)
    if not rankKey then
        return description
    end

    local tier = NX_RANK.getRankForCreature(thing)
    if canSeeRank(player, thing) then
        local line = NX_RANK.REVEAL.LINE_FMT:format(rankKey)
        if NX_RANK.REVEAL.STAFF_SHOW_SCALERS and player:getGroup():getId() >= 3 then
            line = line .. buildScalerString(tier)
        end
        description = description .. string.format("\n%s", line)
    elseif NX_RANK.REVEAL.SHOW_HINT then
        description = description .. string.format("\n%s", NX_RANK.REVEAL.HINT_TEXT)
    end

    return description
end
