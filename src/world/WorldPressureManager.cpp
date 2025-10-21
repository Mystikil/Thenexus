#include "world/WorldPressureManager.hpp"
#include "monster/Rank.hpp"

static WorldPressureManager* g_wpm = nullptr;

WorldPressureManager& WorldPressureManager::get() {
    if (!g_wpm) g_wpm = new WorldPressureManager();
    return *g_wpm;
}

bool WorldPressureManager::loadJson(const std::string&, std::string& err) { err.clear(); return true; }
bool WorldPressureManager::saveJson(const std::string&, std::string& err) const { err.clear(); return true; }

double WorldPressureManager::getPressureBias(const Position&, uint64_t) const {
    return 0.0; // neutral (no bias)
}

void WorldPressureManager::registerKill(const Position&, RankTier, uint64_t) {
    // no-op
}

void WorldPressureManager::decayTouched(uint64_t) {
    // no-op
}
