local keywordHandler = KeywordHandler:new()
local npcHandler = NpcHandler:new(keywordHandler)
NpcSystem.parseParameters(npcHandler)

local shopModule = ShopModule:new()
shopModule:addBuyableItem({'health potion'}, 7618, 50)
shopModule:addBuyableItem({'mana potion'}, 7620, 50)
shopModule:addBuyableItem({'guild crest'}, 24774, { price = 12000, meta = { minTier = 'Honored', economyMin = 100000, label = 'Guild reserve' } })
shopModule:addSellableItem({'trade mission log'}, 1950, { price = 500, meta = { minTier = 'Friendly' } })
npcHandler:addModule(shopModule)

local function greetCallback(cid)
    npcHandler.topic[cid] = 0
    return true
end

local questConfig = NX_REPUTATION_CONFIG.questExample

local function creatureSayCallback(cid, type, msg)
    if not npcHandler:isFocused(cid) then
        return false
    end

    local player = Player(cid)
    if msgcontains(msg, 'mission') then
        local factionId = ReputationEconomy.getFactionId(questConfig.requiredFaction)
        if not ReputationEconomy.hasTier(player, factionId, questConfig.requiredTier) then
            npcHandler:say('Earn the trust of our guild before asking for assignments.', cid)
            return true
        end
        local status = player:getStorageValue(questConfig.missionStorage)
        if status < 1 then
            npcHandler:say('We need fresh supplies. Bring me three parcels of trade goods and say {deliver}.', cid)
            npcHandler.topic[cid] = 1
        else
            npcHandler:say('Complete your current delivery before asking for another.', cid)
        end
    elseif msgcontains(msg, 'deliver') and npcHandler.topic[cid] == 1 then
        if player:removeItem(2595, 3) then
            player:setStorageValue(questConfig.missionStorage, 1)
            ReputationEconomy.addReputation(player, ReputationEconomy.getFactionId(questConfig.requiredFaction), 180, 'quest_delivery', { npc = 'Faction Quartermaster' })
            npcHandler:say('Excellent. The guild will remember your diligence.', cid)
            npcHandler.topic[cid] = 0
        else
            npcHandler:say('You still owe us three parcels.', cid)
        end
    elseif msgcontains(msg, 'trade') then
        npcHandler:say('Say {offer} if you wish to browse the ledgers.', cid)
    end
    return true
end

npcHandler:setCallback(CALLBACK_GREET, greetCallback)
npcHandler:setCallback(CALLBACK_MESSAGE_DEFAULT, creatureSayCallback)

ReputationEconomy.setNpcFaction(npcHandler, 'Traders Guild', { npcName = 'Faction Quartermaster' })

function onCreatureAppear(cid) npcHandler:onCreatureAppear(cid) end
function onCreatureDisappear(cid) npcHandler:onCreatureDisappear(cid) end
function onCreatureSay(cid, type, msg) npcHandler:onCreatureSay(cid, type, msg) end
function onThink() npcHandler:onThink() end

npcHandler:addModule(FocusModule:new())
