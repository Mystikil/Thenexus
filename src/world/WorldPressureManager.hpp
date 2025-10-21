// Copyright 2023 The Forgotten Server Authors. All rights reserved.
// Use of this source code is governed by the GPL-2.0 License that can be found in the LICENSE file.

#pragma once

#include <cstddef>
#include <cstdint>
#include <string>
#include <unordered_map>
#include <unordered_set>

#include "monster/Rank.hpp"
#include "position.h"

struct RegionID {
	int16_t rx = 0;
	int16_t ry = 0;
	int16_t z = 0;

	bool operator==(const RegionID& other) const
	{
		return rx == other.rx && ry == other.ry && z == other.z;
	}
};

struct RegionHasher {
	size_t operator()(const RegionID& region) const noexcept
	{
		const uint64_t x = static_cast<uint16_t>(region.rx);
		const uint64_t y = static_cast<uint16_t>(region.ry);
		const uint64_t z = static_cast<uint16_t>(region.z);
		return (x << 32u) ^ (y << 16u) ^ z;
	}
};

struct RankPressure {
	uint32_t kills[RankCount] {};
	double intensity = 0.0;
	uint64_t lastUpdateMs = 0;
	uint32_t recentOutbreaks = 0;
};

class WorldPressureManager {
	public:
		static WorldPressureManager& get();

		bool loadJson(const std::string& path, std::string& err);
		bool saveJson(const std::string& path, std::string& err) const;

		void registerKill(const Position& pos, RankTier tier, uint64_t nowMs);
		double getPressureBias(const Position& pos, uint64_t nowMs) const;
		void decayTouched(uint64_t nowMs);

	private:
		WorldPressureManager() = default;

		RegionID toRegion(const Position& pos) const;
		void touchDecay(RankPressure& pressure, uint64_t nowMs, double decayPerMinute) const;

		using PressureMap = std::unordered_map<RegionID, RankPressure, RegionHasher>;

		mutable PressureMap pressureByRegion;
		mutable std::unordered_set<RegionID, RegionHasher> touchedRegions;
};

