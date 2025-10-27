#include "utils/Logger.h"

#include <array>
#include <chrono>
#include <cstdlib>
#include <iomanip>
#include <iostream>
#include <sstream>
#include <system_error>

#include <boost/algorithm/string.hpp>

#if defined(_WIN32)
#include <Windows.h>
#elif defined(__linux__)
#include <limits.h>
#include <unistd.h>
#endif

namespace {
        std::filesystem::path determineBasePath() {
        #if defined(_WIN32)
                std::wstring buffer(MAX_PATH, L'\0');
                DWORD length = GetModuleFileNameW(nullptr, buffer.data(), static_cast<DWORD>(buffer.size()));
                if (length == 0 || length == buffer.size()) {
                        return std::filesystem::current_path();
                }
                buffer.resize(length);
                return std::filesystem::path(buffer).parent_path();
        #elif defined(__linux__)
                std::array<char, PATH_MAX> buffer{};
                ssize_t length = readlink("/proc/self/exe", buffer.data(), buffer.size() - 1);
                if (length <= 0) {
                        return std::filesystem::current_path();
                }
                buffer[static_cast<std::size_t>(length)] = '\0';
                return std::filesystem::path(buffer.data()).parent_path();
        #else
                return std::filesystem::current_path();
        #endif
        }

        std::string timestampString() {
                auto now = std::chrono::system_clock::now();
                auto time = std::chrono::system_clock::to_time_t(now);
                std::tm tm{};
        #if defined(_WIN32)
                localtime_s(&tm, &time);
        #else
                localtime_r(&time, &tm);
        #endif
                std::ostringstream ss;
                ss << std::put_time(&tm, "%Y-%m-%d %H:%M:%S");
                return ss.str();
        }
} // namespace

Logger& Logger::instance() {
        static Logger logger;
        return logger;
}

void Logger::setLogFile(const std::filesystem::path& path, std::uintmax_t maxBytes, std::size_t maxFiles) {
        std::lock_guard<std::mutex> lock(mutex_);
        logFilePath_ = path;
        maxBytes_ = maxBytes;
        maxFiles_ = std::max<std::size_t>(1, maxFiles);

        const auto fullPath = makePath(logFilePath_);
        std::filesystem::create_directories(fullPath.parent_path());

        logStream_.close();
        logStream_.open(fullPath, std::ios::out | std::ios::app | std::ios::binary);
}

void Logger::setConsole(bool enabled) {
        std::lock_guard<std::mutex> lock(mutex_);
        consoleEnabled_ = enabled;
}

void Logger::setLevel(LogLevel level) {
        std::lock_guard<std::mutex> lock(mutex_);
        level_ = parseLevelFromEnv(level);
}

void Logger::log(LogLevel level, std::string_view message) {
        std::lock_guard<std::mutex> lock(mutex_);
        if (level < level_) {
                return;
        }

        const std::string formatted = fmt::format("{} [{}] {}\n", timestampString(), levelToString(level), message);
        write(formatted);

        if (level == LogLevel::Fatal) {
                logStream_.flush();
        }
}

void Logger::trace(std::string_view message) {
        log(LogLevel::Trace, message);
}

void Logger::debug(std::string_view message) {
        log(LogLevel::Debug, message);
}

void Logger::info(std::string_view message) {
        log(LogLevel::Info, message);
}

void Logger::warn(std::string_view message) {
        log(LogLevel::Warn, message);
}

void Logger::error(std::string_view message) {
        log(LogLevel::Error, message);
}

void Logger::fatal(std::string_view message) {
        log(LogLevel::Fatal, message);
}

std::filesystem::path Logger::getLogFilePath() const {
        std::lock_guard<std::mutex> lock(mutex_);
        return logFilePath_;
}

void Logger::flush() {
        std::lock_guard<std::mutex> lock(mutex_);
        if (logStream_.is_open()) {
                        logStream_.flush();
        }
        std::cout.flush();
        std::clog.flush();
}

void Logger::rotateIfNeeded(std::size_t messageBytes) {
        if (!logStream_.is_open() || maxBytes_ == 0 || logFilePath_.empty()) {
                return;
        }

        const auto fullPath = makePath(logFilePath_);
        std::uintmax_t currentSize = 0;
        if (std::filesystem::exists(fullPath)) {
                currentSize = std::filesystem::file_size(fullPath);
        }

        if (currentSize + messageBytes <= maxBytes_) {
                return;
        }

        logStream_.close();

        for (std::size_t index = maxFiles_; index > 0; --index) {
                std::filesystem::path source = (index == 1) ? fullPath : fullPath.string() + "." + std::to_string(index - 1);
                if (!std::filesystem::exists(source)) {
                        continue;
                }
                std::filesystem::path destination = fullPath.string() + "." + std::to_string(index);
                std::error_code ec;
                std::filesystem::remove(destination, ec);
                std::filesystem::rename(source, destination, ec);
        }

        logStream_.open(fullPath, std::ios::out | std::ios::trunc | std::ios::binary);
}

void Logger::write(std::string_view formattedMessage) {
        rotateIfNeeded(formattedMessage.size());

        if (logStream_.is_open()) {
                logStream_ << formattedMessage;
        }

        if (consoleEnabled_) {
                std::cout << formattedMessage;
        }
}

std::string Logger::levelToString(LogLevel level) {
        switch (level) {
                case LogLevel::Trace: return "TRACE";
                case LogLevel::Debug: return "DEBUG";
                case LogLevel::Info: return "INFO";
                case LogLevel::Warn: return "WARN";
                case LogLevel::Error: return "ERROR";
                case LogLevel::Fatal: return "FATAL";
        }
        return "INFO";
}

LogLevel Logger::parseLevelFromEnv(LogLevel fallback) {
        if (const char* env = std::getenv("NEXUS_LOG_LEVEL")) {
                std::string value(env);
                boost::algorithm::to_upper(value);
                if (value == "TRACE") { return LogLevel::Trace; }
                if (value == "DEBUG") { return LogLevel::Debug; }
                if (value == "INFO") { return LogLevel::Info; }
                if (value == "WARN") { return LogLevel::Warn; }
                if (value == "ERROR") { return LogLevel::Error; }
                if (value == "FATAL") { return LogLevel::Fatal; }
        }
        return fallback;
}

std::filesystem::path makePath(const std::filesystem::path& relative) {
        static const std::filesystem::path base = determineBasePath();
        if (relative.is_absolute()) {
                return relative;
        }
        return base / relative;
}

