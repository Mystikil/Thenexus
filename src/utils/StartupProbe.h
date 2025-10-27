#pragma once

#include <chrono>
#include <string>

class StartupProbe {
        public:
                static void initialize();
                static void shutdown();

                static void setWatchdogThreshold(std::chrono::milliseconds threshold);

                // Passing nullptr or an empty string will finalize the current phase without starting a new one.
                static void mark(const char* phase);

        private:
                static void watchdogLoop();
};

