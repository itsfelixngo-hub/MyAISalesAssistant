module.exports = {
  async up(db) {
    try {
      await db.collection('contacts').dropIndex('senderMail_1_senderTel_1_senderName_1_lang_1');
      console.log('Index dropped');
    } catch (err) {
      console.log('Index may not exist:', err.message);
    }
  },

  async down(db) {
    await db.collection('contacts').createIndex(
      { senderMail: 1, senderTel: 1, senderName: 1, lang: 1 },
      { unique: true }
    );
  }
};