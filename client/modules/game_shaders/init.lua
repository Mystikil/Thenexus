-- otclient/modules/game_shaders/init.lua

Module.name = "game_shaders"
Module.description = "Item rarity shader system"
Module.version = "1.0"

function init()
  dofile("rarity.lua")
end

function terminate()
end
