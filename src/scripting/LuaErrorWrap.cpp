#include "scripting/LuaErrorWrap.h"

#include "utils/Logger.h"

#include <cstdlib>

#include <fmt/format.h>

extern "C" {
#include <lua.hpp>
}

namespace {
        int traceback(lua_State* L) {
                const char* message = lua_tostring(L, 1);
                if (message == nullptr) {
                        if (luaL_callmeta(L, 1, "__tostring") && lua_type(L, -1) == LUA_TSTRING) {
                                return 1;
                        }
                        message = luaL_typename(L, 1);
                }

                luaL_traceback(L, L, message, 1);
                return 1;
        }
}

int pushTraceback(lua_State* L) {
        lua_pushcfunction(L, traceback);
        return lua_gettop(L);
}

bool pcallWithTrace(lua_State* L, int nargs, int nresults, const std::string& context) {
        int base = lua_gettop(L) - nargs;
        pushTraceback(L);
        lua_insert(L, base);

        int status = lua_pcall(L, nargs, nresults, base);
        lua_remove(L, base);

        if (status != LUA_OK) {
                const char* err = lua_tostring(L, -1);
                std::string message = err ? err : "(unknown error)";
                if (!context.empty()) {
                        Logger::instance().error(fmt::format("Lua error [{}]: {}", context, message));
                }
                return false;
        }
        return true;
}

int luaPanic(lua_State* L) {
        const char* err = lua_tostring(L, -1);
        std::string message = err ? err : "(unknown panic)";
        Logger::instance().fatal(fmt::format("Lua panic: {}", message));
        std::abort();
}

