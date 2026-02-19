# Contributing to Laravel Migration Searcher

Thank you for considering contributing to Laravel Migration Searcher!

## How to Contribute

### Reporting Bugs

If you find a bug, please open an issue on GitHub with:
- Clear title and description
- Laravel version
- PHP version
- Steps to reproduce
- Expected vs actual behavior
- Example migration code (if applicable)

### Suggesting Features

Feature suggestions are welcome! Please:
- Check existing issues first
- Describe the use case
- Explain why it would be useful
- Provide examples if possible

### Pull Requests

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Make your changes**
4. **Test thoroughly** (add example migrations that test your changes)
5. **Commit your changes** (`git commit -m 'Add amazing feature'`)
6. **Push to the branch** (`git push origin feature/amazing-feature`)
7. **Open a Pull Request**

### Coding Standards

- Follow PSR-12 coding standard
- Write clear, descriptive commit messages
- Comment complex logic
- Keep backward compatibility when possible

### Testing

Currently, the package doesn't have automated tests. When adding features:
- Test manually with various migration types
- Test with large migration sets (100+)
- Test edge cases (empty paths, invalid configs)

### Documentation

- Update README.md if adding features
- Update CHANGELOG.md
- Add inline code comments for complex logic
- Update config file with new options

## Development Setup

```bash
# Clone your fork
git clone https://github.com/AdrianKuriata/claude-skill-laravel-migration-searcher.git

# Install in a Laravel project for testing
# In your Laravel project:
composer require devsite/claude-skill-laravel-migration-searcher

# Or use local path
composer config repositories.local '{"type": "path", "url": "../laravel-migration-searcher"}'
composer require devsite/claude-skill-laravel-migration-searcher:@dev
```

## Questions?

Feel free to open an issue for any questions about contributing!

---

**Thank you for making Laravel Migration Searcher better!** 🎉
