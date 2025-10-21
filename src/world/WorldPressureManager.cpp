// Copyright 2023 The Forgotten Server Authors. All rights reserved.
// Use of this source code is governed by the GPL-2.0 License that can be found in the LICENSE file.

#include "otpch.h"

#include "world/WorldPressureManager.hpp"

#include <algorithm>
#include <cmath>
#include <fstream>
#include <limits>

#include "monster/Rank.hpp"
#include "thirdparty/json.hpp"

WorldPressureManager& WorldPressureManager::get()
{
	static WorldPressureManager instance;
	return instance;
}

bool WorldPressureManager::loadJson(const std::string& path, std::string& err)
{
	std::ifstream input(path);
	if (!input.is_open()) {
		err = "Could not open world pressure file: " + path;
		return false;
	}

	nlohmann::json document;
	try {
		input >> document;
	} catch (const std::exception& e) {
		err = std::string("Failed to parse world pressure file: ") + e.what();
		return false;
	}

	if (!document.is_object()) {
		err = "World pressure root must be an object";
		return false;
	}

	pressureByRegion.clear();
	touchedRegions.clear();

	const auto regionsIt = document.find("regions");
	if (regionsIt != document.end() && regionsIt->is_array()) {
		for (const auto& entry : *regionsIt) {
			if (!entry.is_object()) {
				continue;
			}

			RegionID region;
			region.rx = static_cast<int16_t>(entry.value("rx", 0));
			region.ry = static_cast<int16_t>(entry.value("ry", 0));
			region.z = static_cast<int16_t>(entry.value("z", 0));

			RankPressure pressure;
			pressure.intensity = std::max(0.0, entry.value("intensity", pressure.intensity));
			pressure.lastUpdateMs = entry.value("lastUpdateMs", pressure.lastUpdateMs);
			if (const auto recentIt = entry.find("recentOutbreaks"); recentIt != entry.end() && recentIt->is_number_unsigned()) {
				const uint64_t value = recentIt->get<uint64_t>();
				pressure.recentOutbreaks = static_cast<uint32_t>(std::min<uint64_t>(value, std::numeric_limits<uint32_t>::max()));
			}

			if (const auto killsIt = entry.find("kills"); killsIt != entry.end() && killsIt->is_array()) {
				size_t index = 0;
				for (const auto& killEntry : *killsIt) {
					if (!killEntry.is_number_unsigned()) {
						++index;
						continue;
					}
					if (index >= RankCount) {
						break;
					}
					const uint64_t value = killEntry.get<uint64_t>();
					pressure.kills[index] = static_cast<uint32_t>(std::min<uint64_t>(value, std::numeric_limits<uint32_t>::max()));
					++index;
				}
			}

			pressureByRegion[region] = pressure;
		}
	}

	return true;
}

bool WorldPressureManager::saveJson(const std::string& path, std::string& err) const
{
	nlohmann::json document;
	auto& regions = document["regions"]; // NOLINT(cppcoreguidelines-pro-type-union-access)
	regions = nlohmann::json::array();

	for (const auto& [region, pressure] : pressureByRegion) {
		nlohmann::json entry;
		entry["rx"] = region.rx;
		entry["ry"] = region.ry;
		entry["z"] = region.z;
		entry["intensity"] = pressure.intensity;
		entry["lastUpdateMs"] = pressure.lastUpdateMs;
		entry["recentOutbreaks"] = pressure.recentOutbreaks;

		nlohmann::json killsJson = nlohmann::json::array();
		for (size_t i = 0; i < RankCount; ++i) {
			killsJson.push_back(pressure.kills[i]);
		}
		entry["kills"] = std::move(killsJson);

		regions.push_back(std::move(entry));
	}

	std::ofstream output(path);
	if (!output.is_open()) {
		err = "Could not open world pressure file for writing: " + path;
		return false;
	}

	try {
		output << document.dump(2);
	} catch (const std::exception& e) {
		err = std::string("Failed to write world pressure file: ") + e.what();
		return false;
	}

	return true;
}

void WorldPressureManager::registerKill(const Position& pos, RankTier tier, uint64_t nowMs)
{
	const RegionID region = toRegion(pos);
	RankPressure& pressure = pressureByRegion[region];
	touchedRegions.insert(region);

	const RankConfig& cfg = RankSystem::get().config();
	touchDecay(pressure, nowMs, cfg.pressureDecayPerMinute);

	if (tier != RankTier::None) {
		const size_t index = static_cast<size_t>(tier);
		if (index < RankCount) {
			++pressure.kills[index];
		}

		double delta = 0.0;
		if (const char* name = RankSystem::get().toString(tier)) {
			if (const auto intensityIt = cfg.intensityPerKillByRank.find(name); intensityIt != cfg.intensityPerKillByRank.end()) {
				delta = intensityIt->second;
			}
		}
		pressure.intensity += std::max(0.0, delta);
	}

	++pressure.recentOutbreaks;
	pressure.lastUpdateMs = nowMs;
}

double WorldPressureManager::getPressureBias(const Position& pos, uint64_t nowMs) const
{
	auto& map = pressureByRegion;
	const RegionID region = toRegion(pos);
	const auto it = map.find(region);
	if (it == map.end()) {
		return 0.0;
	}

	auto& touched = touchedRegions;
	touched.insert(region);

	const RankConfig& cfg = RankSystem::get().config();
	RankPressure& pressure = it->second;
	touchDecay(pressure, nowMs, cfg.pressureDecayPerMinute);

	const double normalized = std::clamp(pressure.intensity, 0.0, 1.0);
	return normalized * cfg.biasScale;
}

void WorldPressureManager::decayTouched(uint64_t nowMs)
{
	if (touchedRegions.empty()) {
		return;
	}

	const RankConfig& cfg = RankSystem::get().config();
	for (const auto& region : touchedRegions) {
		const auto it = pressureByRegion.find(region);
		if (it == pressureByRegion.end()) {
			continue;
		}
		touchDecay(it->second, nowMs, cfg.pressureDecayPerMinute);
	}

	touchedRegions.clear();
}

RegionID WorldPressureManager::toRegion(const Position& pos) const
{
	RegionID region;
	region.rx = static_cast<int16_t>(pos.getX() >> 7);
	region.ry = static_cast<int16_t>(pos.getY() >> 7);
	region.z = static_cast<int16_t>(pos.getZ());
	return region;
}

void WorldPressureManager::touchDecay(RankPressure& pressure, uint64_t nowMs, double decayPerMinute) const
{
	if (pressure.lastUpdateMs == 0) {
		pressure.lastUpdateMs = nowMs;
		return;
	}
	if (nowMs <= pressure.lastUpdateMs) {
		return;
	}

	const double minutes = static_cast<double>(nowMs - pressure.lastUpdateMs) / 60000.0;
	pressure.intensity *= std::pow(decayPerMinute, minutes);
	pressure.lastUpdateMs = nowMs;
}

