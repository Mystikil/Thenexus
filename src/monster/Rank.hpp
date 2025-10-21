#pragma once
#include <string>
#include <unordered_map>
#include <vector>
#include <optional>
#include <cstdint>

enum class RankTier : uint8_t { F, E, D, C, B, A, S, SS, SSS, None };

struct RankScalars {
    double hp = 1.0;
    double dmg = 1.0;         // outgoing damage mult
    double mit = 0.0;         // incoming mitigation (0..0.80)
    int32_t speedDelta = 0;   // +speed
    double xp = 1.0;
    double lootMult = 1.0;
    uint8_t extraRolls = 0;
    double aiCdMult = 1.0;    // boss AI cooldown multiplier
    uint8_t spellUnlock = 0;
    int32_t resist = 0;       // treat as % (0..100) when applying
};

struct RankDef {
    std::string name; // "F".."SSS"
    RankScalars s;
};

struct RankDistribution {
    std::unordered_map<RankTier, uint32_t> weights;
};

struct RankConfig {
    bool enabled = false;
    std::vector<RankDef> order;
    RankDistribution globalDist;
    std::unordered_map<std::string, RankDistribution> byZone;
    std::unordered_map<std::string, RankDistribution> byMonsterName;
};

class Monster; // fwd

class RankSystem {
public:
    static RankSystem& get();

    bool loadFromJson(const std::string& path, std::string& err);
    const RankConfig& config() const { return cfg; }
    bool isEnabled() const { return cfg.enabled; }

    const RankDef* def(RankTier t) const;
    std::optional<RankTier> parseTier(const std::string& name) const;
    const char* toString(RankTier t) const;

    RankTier pick(const std::string& zoneTag, const std::string& monsterKey) const;

    // Optional helpers referenced by callers; provide simple implementations
    RankTier clampedAdvance(RankTier base, int delta) const;
    RankTier pickBaseTier(const std::string& monsterKey) const;
    int biasToOffset(double bias) const;

    // Apply all scalar effects that can be applied immediately (HP/speed)
    void applyScalars(Monster& m, RankTier t) const;

private:
    RankConfig cfg;

    uint32_t totalWeight(const RankDistribution& d) const;
    RankTier pickFrom(const RankDistribution& d) const;
};
