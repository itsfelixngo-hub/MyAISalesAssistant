module.exports = {
  /**
   * @param db {import('mongodb').Db}
   * @param client {import('mongodb').MongoClient}
   * @returns {Promise<void>}
   */
  async up(db, client) {
    const col = db.collection('posts');

    // Tạo index 1: (lang, slug) unique + partial
    await col.createIndex(
      { lang: 1, slug: 1 },
      {
        name: 'ux_posts_lang_slug_partial',
        unique: true,
        partialFilterExpression: {
          slug: { $exists: true, $type: 'string' },
        },
      },
    );

    // Tạo index 2: (lang, slugOld) unique + partial
    await col.createIndex(
      { lang: 1, slugOld: 1 },
      {
        name: 'ux_posts_lang_slugOld_partial',
        unique: true,
        partialFilterExpression: {
          slugOld: { $exists: true, $type: 'string' },
        },
      },
    );
  },

  /**
   * @param db {import('mongodb').Db}
   * @param client {import('mongodb').MongoClient}
   * @returns {Promise<void>}
   */
  async down(db, client) {
    const col = db.collection('posts');

    // Rollback: drop theo name (an toàn hơn drop theo key)
    // Nếu index không tồn tại thì bỏ qua (để down không fail)
    const safeDrop = async (name) => {
      try {
        await col.dropIndex(name);
      } catch (e) {
        // IndexNotFound: ignore
        if (e?.codeName !== 'IndexNotFound' && !String(e?.message || '').includes('index not found')) {
          throw e;
        }
      }
    };

    await safeDrop('ux_posts_lang_slug_partial');
    await safeDrop('ux_posts_lang_slugOld_partial');
  },
};
