-- Compatibility library for our old Lua API AND Compatibility with OPCODE JSON
dofile('data/lib/compat/compat.lua')
dofile('data/lib/compat/json.lua')

-- Core API functions implemented in Lua
dofile('data/lib/core/core.lua')

-- Debugging helper function for Lua developers
dofile('data/lib/debugging/dump.lua')
dofile('data/lib/debugging/lua_version.lua')

-- Serialization helpers
dofile('data/lib/serialization.lua')
dofile('data/lib/nx_rank.lua')
dofile('data/lib/nx_boss.lua')
dofile('data/lib/nx_reputation_config.lua')
dofile('data/lib/nx_reputation.lua')
