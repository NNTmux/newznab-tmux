# We will rename scrape into run_ircscraper. It should be fast.
UPDATE tmux SET setting = "run_ircscraper" WHERE setting = "scrape";
