local bind = CreatureEvent('ECHOAutoBind')

function bind.onSpawn(creature)
    if not creature or not creature:isMonster() then
        return true
    end
    creature:registerEvent('ECHOThink')
    return true
end

bind:register()
