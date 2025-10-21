#pragma once
#include <string>
#include <cstdint>
#include "position.h"

enum class RankTier : uint8_t;

class WorldPressureManager {
public:
    static WorldPressureManager& get();

    bool loadJson(const std::string& /*path*/, std::string& err);
    bool saveJson(const std::string& /*path*/, std::string& err) const;

    double getPressureBias(const Position& /*pos*/, uint64_t /*partyKey*/) const; // -1..+1
    void registerKill(const Position& /*pos*/, RankTier /*tier*/, uint64_t /*partyKey*/);
    void decayTouched(uint64_t /*partyKey*/);

private:
    WorldPressureManager() = default;
};
