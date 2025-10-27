#pragma once

#include <string>

struct lua_State;

int pushTraceback(lua_State* L);
bool pcallWithTrace(lua_State* L, int nargs, int nresults, const std::string& context);
int luaPanic(lua_State* L);

