local function log(message)
    print('[ReputationEconomy] ' .. message)
end

function onStartup()
    if ReputationEconomy then
        ReputationEconomy.onStartup()
        log('initialized pools and caches')
    end
    return true
end

function onThink(interval)
    if not ReputationEconomy then
        return true
    end
    local applied = ReputationEconomy.flushEconomyLedger()
    if applied > 0 then
        log('applied ' .. applied .. ' ledger entries')
    end
    local marketProcessed = ReputationEconomy.captureMarketFees()
    if marketProcessed > 0 then
        log('captured ' .. marketProcessed .. ' market transactions')
    end
    return true
end

function onTime(interval)
    if ReputationEconomy then
        local decayed = ReputationEconomy.applyDecay()
        if decayed > 0 then
            log('applied decay to ' .. decayed .. ' player rows')
        end
    end
    return true
end
