-- nx_rank_apply.lua
-- Creature spawn hook responsible for assigning ranks and initial stat
-- modifications. Delegates the heavy lifting to NX_RANK helper functions.

function onSpawn(monster)
    if not monster or not monster:isMonster() then
        return true
    end

    local areaTag = NX_RANK.resolveAreaTag(monster)
    monster:setStorageValue(NX_RANK.STORAGE.area, areaTag)

    local monsterKey = monster:getName():lower()
    local rankKey, tier = NX_RANK.pickRank(areaTag, monsterKey)
    if not tier then
        return true
    end

    NX_RANK.setRank(monster, rankKey)
    NX_RANK.decorateName(monster, rankKey)
    NX_RANK.applyTier(monster, tier)

    return true
end
