#include "otpch.h"

#include "luascript.h"

#if ENABLE_INSTANCING
#include "condition.h"
#include "game/game.h"
#include "game/InstanceManager.h"
#include "map.h"

extern Game g_game;
extern InstanceManager g_instances;

namespace {
	void pushPositionTable(lua_State* L, const Position& position) {
		lua_createtable(L, 0, 3);
		lua_pushinteger(L, position.x);
		lua_setfield(L, -2, "x");
		lua_pushinteger(L, position.y);
		lua_setfield(L, -2, "y");
		lua_pushinteger(L, position.z);
		lua_setfield(L, -2, "z");
	}

	void pushInstanceRulesTable(lua_State* L, const InstanceRules& rules) {
		lua_createtable(L, 0, 6);
		lua_pushnumber(L, rules.expRate);
		lua_setfield(L, -2, "expRate");
		lua_pushnumber(L, rules.lootRate);
		lua_setfield(L, -2, "lootRate");
		lua::pushString(L, rules.pvpMode);
		lua_setfield(L, -2, "pvpMode");
		lua_pushboolean(L, rules.permadeath);
		lua_setfield(L, -2, "permadeath");
		lua_pushboolean(L, rules.bindOnEntry);
		lua_setfield(L, -2, "bindOnEntry");
		lua_pushboolean(L, rules.persistent);
		lua_setfield(L, -2, "persistent");
	}

	void pushInstanceSpecTable(lua_State* L, const InstanceSpec& spec) {
		lua_createtable(L, 0, 7);
		lua_pushinteger(L, spec.id);
		lua_setfield(L, -2, "id");
		lua::pushString(L, spec.name);
		lua_setfield(L, -2, "name");
		lua::pushString(L, spec.otbm);
		lua_setfield(L, -2, "otbm");

		pushPositionTable(L, spec.spawn);
		lua_setfield(L, -2, "spawn");

		pushInstanceRulesTable(L, spec.rules);
		lua_setfield(L, -2, "rules");

		lua_pushboolean(L, spec.persistent);
		lua_setfield(L, -2, "persistent");
	}
} // namespace
#endif

int LuaScriptInterface::luaGameTransferPlayerToInstance(lua_State* L) {
#if !ENABLE_INSTANCING
	lua_pushboolean(L, false);
	lua::pushString(L, "instancing disabled");
	return 2;
#else
	Player* player = lua::getPlayer(L, 1);
	if (!player) {
		lua_pushboolean(L, false);
		lua::pushString(L, "player not found");
		return 2;
	}

	InstanceId id = lua::getNumber<InstanceId>(L, 2);
	if (!g_instances.ensureLoaded(id)) {
		lua_pushboolean(L, false);
		lua::pushString(L, "instance not found");
		return 2;
	}

	const int argumentCount = lua_gettop(L);
	const bool hasPosition = argumentCount >= 3 && lua_istable(L, 3) != 0;
	Position destination;
	if (hasPosition) {
		destination = lua::getPosition(L, 3);
	}

	const bool force = lua::getBoolean(L, hasPosition ? 4 : 3, false);
	if (!force && player->hasCondition(CONDITION_INFIGHT)) {
		lua_pushboolean(L, false);
		lua::pushString(L, "in fight");
		return 2;
	}

	g_instances.onPlayerLeave(player->getInstanceId());

        const InstanceSpec* spec = g_instances.getSpec(id);
        Position targetPosition = hasPosition ? destination : (spec ? spec->spawn : Position{});

        if (hasPosition) {
                Map* instanceMap = g_instances.getMap(id);
                const bool validDestination = instanceMap && instanceMap->getTile(targetPosition) != nullptr;
                if (!validDestination && spec) {
                        targetPosition = spec->spawn;
                }
        }

        player->stopWalk();
        player->removeFollowCreature();
        player->removeAttackedCreature();
        player->sendCancelTarget();

        player->setInstanceId(id);
        g_game.internalTeleport(player, targetPosition, true, true);
        g_instances.onPlayerEnter(id);

	lua_pushboolean(L, true);
	lua_pushnil(L);
	return 2;
#endif
}

int LuaScriptInterface::luaGameGetInstanceSpec(lua_State* L) {
#if !ENABLE_INSTANCING
	lua_pushnil(L);
	return 1;
#else
	const InstanceId id = lua::getNumber<InstanceId>(L, 1);
	const InstanceSpec* spec = g_instances.getSpec(id);
	if (!spec) {
		lua_pushnil(L);
		return 1;
	}

	pushInstanceSpecTable(L, *spec);
	return 1;
#endif
}

int LuaScriptInterface::luaGameListInstances(lua_State* L) {
#if !ENABLE_INSTANCING
	lua_newtable(L);
	return 1;
#else
	const auto& specs = g_instances.configured();
	lua_createtable(L, static_cast<int>(specs.size()), 0);

	int index = 0;
	for (const auto& spec : specs) {
		lua_createtable(L, 0, 2);
		lua_pushinteger(L, spec.id);
		lua_setfield(L, -2, "id");
		lua::pushString(L, spec.name);
		lua_setfield(L, -2, "name");
		lua_rawseti(L, -2, ++index);
	}
	return 1;
#endif
}

int LuaScriptInterface::luaGameListActiveInstances(lua_State* L) {
#if !ENABLE_INSTANCING
	lua_newtable(L);
	return 1;
#else
	const auto ids = g_instances.active();
	lua_createtable(L, static_cast<int>(ids.size()), 0);

	int index = 0;
	for (InstanceId activeId : ids) {
		lua_pushinteger(L, activeId);
		lua_rawseti(L, -2, ++index);
	}
	return 1;
#endif
}

void registerGameInstanceBindings(lua_State* L) {
	lua::registerMethod(L, "Game", "transferPlayerToInstance", LuaScriptInterface::luaGameTransferPlayerToInstance);
	lua::registerMethod(L, "Game", "getInstanceSpec", LuaScriptInterface::luaGameGetInstanceSpec);
	lua::registerMethod(L, "Game", "listInstances", LuaScriptInterface::luaGameListInstances);
	lua::registerMethod(L, "Game", "listActiveInstances", LuaScriptInterface::luaGameListActiveInstances);
}
